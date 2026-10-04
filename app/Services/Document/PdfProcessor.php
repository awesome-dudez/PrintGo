<?php

declare(strict_types=1);

namespace App\Services\Document;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class PdfProcessor implements DocumentProcessorInterface
{
    private Parser $parser;

    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? new Parser();
    }

    /**
     * Determine if this processor can handle the given file extension and mime type.
     */
    public function canProcess(string $extension, string $mimeType): bool
    {
        $ext = strtolower(ltrim($extension, '.'));
        $mime = strtolower($mimeType);

        return $ext === 'pdf' || str_contains($mime, 'pdf');
    }

    /**
     * Get the total page count of the PDF document.
     */
    public function getPageCount(string $filePath): int
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return 1;
        }

        try {
            $pdf = $this->parser->parseFile($filePath);
            $pages = $pdf->getPages();
            $count = count($pages);

            if ($count > 0) {
                return $count;
            }
        } catch (\Throwable $e) {
            Log::warning('PdfParser failed to parse PDF page count, using regex fallback', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }

        // Fast fallback: count /Type /Page objects in raw PDF content
        return $this->countPagesFromRawPdf($filePath);
    }

    /**
     * Generate preview images for the PDF pages.
     *
     * @param string $filePath Absolute path to source PDF
     * @param string $outputDir Directory where generated preview images will be stored
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    public function generatePreviews(string $filePath, string $outputDir, array $options = []): array
    {
        File::ensureDirectoryExists($outputDir);
        $pageCount = $this->getPageCount($filePath);
        $maxPages = (int) ($options['max_pages'] ?? 50);
        $pagesToRender = min($pageCount, $maxPages);
        $previews = [];

        // Check if Imagick is available
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            try {
                $imagickPreviews = $this->renderWithImagick($filePath, $outputDir, $pagesToRender);
                if (!empty($imagickPreviews)) {
                    return $imagickPreviews;
                }
            } catch (\Throwable $e) {
                Log::warning('Imagick PDF preview generation failed, falling back to GD placeholder', [
                    'file' => $filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback to standard high-quality GD placeholder previews
        $filename = basename($filePath);
        for ($i = 1; $i <= $pagesToRender; $i++) {
            $targetFileName = sprintf('page_%d.png', $i);
            $targetPath = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $targetFileName;

            $this->createPdfPagePlaceholder(
                outputPath: $targetPath,
                pageNumber: $i,
                totalPages: $pageCount,
                fileName: $filename
            );

            $previews[] = $targetFileName;
        }

        return $previews;
    }

    /**
     * Extract metadata from the PDF file.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(string $filePath): array
    {
        $metadata = [
            'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
            'page_count' => $this->getPageCount($filePath),
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
        ];

        try {
            $pdf = $this->parser->parseFile($filePath);
            $details = $pdf->getDetails();
            if (is_array($details)) {
                $metadata['author'] = $details['Author'] ?? null;
                $metadata['title'] = $details['Title'] ?? null;
                $metadata['creator'] = $details['Creator'] ?? null;
                $metadata['producer'] = $details['Producer'] ?? null;
                $metadata['creation_date'] = $details['CreationDate'] ?? null;
                $metadata['modification_date'] = $details['ModDate'] ?? null;
            }
        } catch (\Throwable $e) {
            // Keep basic metadata
        }

        return $metadata;
    }

    /**
     * Count pages from raw PDF content as a fallback.
     */
    private function countPagesFromRawPdf(string $filePath): int
    {
        try {
            $content = @file_get_contents($filePath);
            if ($content === false) {
                return 1;
            }

            // Look for /Count N in Pages dictionary
            if (preg_match('/\/Count\s+(\d+)/', $content, $matches)) {
                $count = (int) $matches[1];
                if ($count > 0) {
                    return $count;
                }
            }

            // Look for /Type /Page (not /Pages)
            $matchesCount = preg_match_all('/\/Type\s*\/Page(?![sS])\b/', $content);
            if ($matchesCount > 0) {
                return $matchesCount;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return 1;
    }

    /**
     * Render PDF pages using Imagick when available.
     *
     * @return array<int, string>
     */
    private function renderWithImagick(string $filePath, string $outputDir, int $pagesToRender): array
    {
        $previews = [];
        for ($i = 0; $i < $pagesToRender; $i++) {
            $imagick = new \Imagick();
            $imagick->setResolution(150, 150);
            $imagick->readImage(sprintf('%s[%d]', $filePath, $i));
            $imagick->setImageFormat('png');
            $imagick->setImageBackgroundColor('white');
            $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

            $targetFileName = sprintf('page_%d.png', $i + 1);
            $targetPath = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $targetFileName;
            $imagick->writeImage($targetPath);
            $imagick->clear();
            $imagick->destroy();

            $previews[] = $targetFileName;
        }

        return $previews;
    }

    /**
     * Create a standard GD placeholder preview image for a PDF page.
     */
    private function createPdfPagePlaceholder(string $outputPath, int $pageNumber, int $totalPages, string $fileName): void
    {
        $width = 600;
        $height = 848; // Standard A4 ratio (1:1.414)

        $im = imagecreatetruecolor($width, $height);
        if ($im === false) {
            return;
        }

        // Colors
        $white = imagecolorallocate($im, 255, 255, 255);
        $bgGrey = imagecolorallocate($im, 248, 250, 252); // #F8FAFC
        $borderColor = imagecolorallocate($im, 226, 232, 240); // #E2E8F0
        $primary = imagecolorallocate($im, 220, 38, 38); // Red #DC2626 (PDF red)
        $textDark = imagecolorallocate($im, 30, 41, 59); // #1E293B
        $textMuted = imagecolorallocate($im, 100, 116, 139); // #64748B
        $boxBg = imagecolorallocate($im, 254, 242, 242); // Light red

        // Background
        imagefilledrectangle($im, 0, 0, $width, $height, $bgGrey);

        // Document canvas simulation (inner white page with border)
        imagefilledrectangle($im, 20, 20, $width - 20, $height - 20, $white);
        imagerectangle($im, 20, 20, $width - 20, $height - 20, $borderColor);

        // Header banner
        imagefilledrectangle($im, 20, 20, $width - 20, 70, $primary);
        $headerText = 'PDF DOCUMENT PREVIEW';
        imagestring($im, 5, (int) (($width - (strlen($headerText) * 9)) / 2), 35, $headerText, $white);

        // Page badge box
        imagefilledrectangle($im, 150, 140, $width - 150, 220, $boxBg);
        imagerectangle($im, 150, 140, $width - 150, 220, $primary);
        $badgeText = sprintf('PAGE %d OF %d', $pageNumber, $totalPages);
        imagestring($im, 5, (int) (($width - (strlen($badgeText) * 9)) / 2), 170, $badgeText, $primary);

        // Mock document body lines to simulate content
        $lineColor = imagecolorallocate($im, 226, 232, 240);
        $lineColorDarker = imagecolorallocate($im, 203, 213, 225);
        for ($y = 260; $y < $height - 180; $y += 24) {
            $lineWidth = ($y % 48 === 0) ? $width - 120 : $width - 80;
            imagefilledrectangle($im, 60, $y, $lineWidth, $y + 8, ($y % 72 === 0) ? $lineColorDarker : $lineColor);
        }

        // File metadata footer info
        $shortName = strlen($fileName) > 35 ? substr($fileName, 0, 32) . '...' : $fileName;
        $infoText = "File: {$shortName}";
        imagestring($im, 3, (int) (($width - (strlen($infoText) * 7)) / 2), $height - 100, $infoText, $textDark);

        $noticeText = 'Standard Page Preview (A4 Ratio)';
        imagestring($im, 2, (int) (($width - (strlen($noticeText) * 6)) / 2), $height - 70, $noticeText, $textMuted);

        imagepng($im, $outputPath, 8);
        imagedestroy($im);
    }
}
