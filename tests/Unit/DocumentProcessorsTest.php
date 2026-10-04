<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\UnsupportedFileTypeException;
use App\Services\Document\DocxProcessor;
use App\Services\Document\DocumentProcessorFactory;
use App\Services\Document\HeicProcessor;
use App\Services\Document\ImageProcessor;
use App\Services\Document\PdfProcessor;
use App\Services\Document\PptxProcessor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class DocumentProcessorsTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_docs_' . uniqid();
        File::ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_factory_resolves_all_processors(): void
    {
        $factory = new DocumentProcessorFactory();

        $this->assertInstanceOf(PdfProcessor::class, $factory->getProcessor('pdf', 'application/pdf'));
        $this->assertInstanceOf(ImageProcessor::class, $factory->getProcessor('jpg', 'image/jpeg'));
        $this->assertInstanceOf(ImageProcessor::class, $factory->getProcessor('png', 'image/png'));
        $this->assertInstanceOf(ImageProcessor::class, $factory->getProcessor('webp', 'image/webp'));
        $this->assertInstanceOf(DocxProcessor::class, $factory->getProcessor('docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        $this->assertInstanceOf(PptxProcessor::class, $factory->getProcessor('pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'));
        $this->assertInstanceOf(HeicProcessor::class, $factory->getProcessor('heic', 'image/heic'));
    }

    public function test_factory_throws_exception_on_unsupported_file_type(): void
    {
        $factory = new DocumentProcessorFactory();

        $this->expectException(UnsupportedFileTypeException::class);
        $factory->getProcessor('xyz_unknown', 'application/unknown');
    }

    public function test_image_processor_creates_preview_with_adjustments(): void
    {
        $imagePath = $this->tempDir . DIRECTORY_SEPARATOR . 'test.png';

        // Create a 200x200 test image using GD
        $im = imagecreatetruecolor(200, 200);
        $red = imagecolorallocate($im, 255, 0, 0);
        imagefilledrectangle($im, 0, 0, 200, 200, $red);
        imagepng($im, $imagePath);
        imagedestroy($im);

        $processor = new ImageProcessor();
        $this->assertTrue($processor->canProcess('png', 'image/png'));
        $this->assertSame(1, $processor->getPageCount($imagePath));

        $previews = $processor->generatePreviews(
            filePath: $imagePath,
            outputDir: $this->tempDir,
            options: [
                'rotation' => 90,
                'grayscale' => true,
                'brightness' => 10,
                'contrast' => 10,
            ]
        );

        $this->assertCount(1, $previews);
        $previewFile = $this->tempDir . DIRECTORY_SEPARATOR . $previews[0];
        $this->assertFileExists($previewFile);

        $meta = $processor->getMetadata($imagePath);
        $this->assertSame(200, $meta['width']);
        $this->assertSame(200, $meta['height']);
    }

    public function test_pdf_processor_handles_previews_and_metadata(): void
    {
        $pdfPath = $this->tempDir . DIRECTORY_SEPARATOR . 'sample.pdf';
        // Mock a simple PDF structure
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n3 0 obj\n<< /Type /Page >>\nendobj\n4 0 obj\n<< /Type /Page >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        file_put_contents($pdfPath, $pdfContent);

        $processor = new PdfProcessor();
        $this->assertTrue($processor->canProcess('pdf', 'application/pdf'));

        $count = $processor->getPageCount($pdfPath);
        $this->assertGreaterThanOrEqual(1, $count);

        $previews = $processor->generatePreviews($pdfPath, $this->tempDir, ['max_pages' => 2]);
        $this->assertNotEmpty($previews);
        $this->assertFileExists($this->tempDir . DIRECTORY_SEPARATOR . $previews[0]);

        $meta = $processor->getMetadata($pdfPath);
        $this->assertSame('pdf', $meta['extension']);
    }

    public function test_docx_processor_extracts_page_count_and_previews(): void
    {
        $docxPath = $this->tempDir . DIRECTORY_SEPARATOR . 'sample.docx';

        // Create a mock docx zip archive
        $zip = new ZipArchive();
        $zip->open($docxPath, ZipArchive::CREATE);
        $appXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Pages>3</Pages><Words>150</Words></Properties>';
        $zip->addFromString('docProps/app.xml', $appXml);
        $zip->addFromString('word/document.xml', '<w:document><w:body><w:p><w:r><w:t>Hello</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        $processor = new DocxProcessor();
        $this->assertTrue($processor->canProcess('docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        $this->assertSame(3, $processor->getPageCount($docxPath));

        $previews = $processor->generatePreviews($docxPath, $this->tempDir);
        $this->assertCount(3, $previews);
        $this->assertFileExists($this->tempDir . DIRECTORY_SEPARATOR . $previews[0]);

        $meta = $processor->getMetadata($docxPath);
        $this->assertSame(3, $meta['page_count']);
        $this->assertSame(150, $meta['words']);
    }

    public function test_pptx_processor_extracts_slide_count_and_previews(): void
    {
        $pptxPath = $this->tempDir . DIRECTORY_SEPARATOR . 'presentation.pptx';

        // Create a mock pptx zip archive
        $zip = new ZipArchive();
        $zip->open($pptxPath, ZipArchive::CREATE);
        $appXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Slides>4</Slides></Properties>';
        $zip->addFromString('docProps/app.xml', $appXml);
        $zip->addFromString('ppt/slides/slide1.xml', '<p:sld/>');
        $zip->addFromString('ppt/slides/slide2.xml', '<p:sld/>');
        $zip->close();

        $processor = new PptxProcessor();
        $this->assertTrue($processor->canProcess('pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'));
        $this->assertSame(4, $processor->getPageCount($pptxPath));

        $previews = $processor->generatePreviews($pptxPath, $this->tempDir);
        $this->assertCount(4, $previews);
        $this->assertFileExists($this->tempDir . DIRECTORY_SEPARATOR . $previews[0]);
    }

    public function test_heic_processor_generates_placeholder_and_detects_format(): void
    {
        $heicPath = $this->tempDir . DIRECTORY_SEPARATOR . 'photo.heic';
        file_put_contents($heicPath, 'HEIC_MOCK_BYTES');

        $processor = new HeicProcessor();
        $this->assertTrue($processor->canProcess('heic', 'image/heic'));
        $this->assertSame(1, $processor->getPageCount($heicPath));

        $previews = $processor->generatePreviews($heicPath, $this->tempDir);
        $this->assertCount(1, $previews);
        $this->assertFileExists($this->tempDir . DIRECTORY_SEPARATOR . $previews[0]);

        $meta = $processor->getMetadata($heicPath);
        $this->assertSame('heic', $meta['extension']);
    }
}
