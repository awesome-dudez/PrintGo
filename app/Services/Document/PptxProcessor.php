<?php

declare(strict_types=1);

namespace App\Services\Document;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class PptxProcessor implements DocumentProcessorInterface
{
    /**
     * Determine if this processor can handle the given file extension and mime type.
     */
    public function canProcess(string $extension, string $mimeType): bool
    {
        $ext = strtolower(ltrim($extension, '.'));
        $mime = strtolower($mimeType);

        return $ext === 'pptx'
            || $ext === 'ppt'
            || str_contains($mime, 'presentationml')
            || str_contains($mime, 'powerpoint')
            || (str_contains($mime, 'zip') && $ext === 'pptx');
    }

    /**
     * Get the total slide count (used as page count) of the presentation.
     */
    public function getPageCount(string $filePath): int
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return 1;
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return 1;
        }

        $slideCount = 0;

        // 1. Try reading docProps/app.xml for <Slides> tag
        $appXml = $zip->getFromName('docProps/app.xml');
        if ($appXml !== false) {
            if (preg_match('/<Slides>(\d+)<\/Slides>/i', $appXml, $matches)) {
                $count = (int) $matches[1];
                if ($count > 0) {
                    $zip->close();
                    return $count;
                }
            }
        }

        // 2. Count actual slide XML files in ppt/slides/
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name && preg_match('#^ppt/slides/slide\d+\.xml$#i', $name)) {
                $slideCount++;
            }
        }

        $zip->close();

        return max(1, $slideCount);
    }

    /**
     * Generate preview images for PPTX slides.
     *
     * @param string $filePath Absolute path to source PPTX
     * @param string $outputDir Directory where generated preview images will be stored
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    public function generatePreviews(string $filePath, string $outputDir, array $options = []): array
    {
        File::ensureDirectoryExists($outputDir);
        $slideCount = $this->getPageCount($filePath);
        $maxSlides = (int) ($options['max_pages'] ?? 30);
        $slidesToRender = min($slideCount, $maxSlides);
        $previews = [];
        $filename = basename($filePath);

        for ($i = 1; $i <= $slidesToRender; $i++) {
            $targetFileName = sprintf('page_%d.png', $i);
            $targetPath = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $targetFileName;

            $this->createPptxSlidePlaceholder(
                outputPath: $targetPath,
                slideNumber: $i,
                totalSlides: $slideCount,
                fileName: $filename
            );

            $previews[] = $targetFileName;
        }

        return $previews;
    }

    /**
     * Extract metadata from the PPTX file.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(string $filePath): array
    {
        $metadata = [
            'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
            'page_count' => $this->getPageCount($filePath),
            'slide_count' => $this->getPageCount($filePath),
            'extension' => 'pptx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        if (!file_exists($filePath)) {
            return $metadata;
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            // Read docProps/app.xml
            $appXml = $zip->getFromName('docProps/app.xml');
            if ($appXml !== false) {
                if (preg_match('/<Notes>(\d+)<\/Notes>/i', $appXml, $m)) {
                    $metadata['notes_count'] = (int) $m[1];
                }
                if (preg_match('/<HiddenSlides>(\d+)<\/HiddenSlides>/i', $appXml, $m)) {
                    $metadata['hidden_slides'] = (int) $m[1];
                }
            }

            // Read docProps/core.xml
            $coreXml = $zip->getFromName('docProps/core.xml');
            if ($coreXml !== false) {
                if (preg_match('/<dc:title>([^<]+)<\/dc:title>/i', $coreXml, $m)) {
                    $metadata['title'] = html_entity_decode($m[1]);
                }
                if (preg_match('/<dc:creator>([^<]+)<\/dc:creator>/i', $coreXml, $m)) {
                    $metadata['creator'] = html_entity_decode($m[1]);
                }
                if (preg_match('/<cp:lastModifiedBy>([^<]+)<\/cp:lastModifiedBy>/i', $coreXml, $m)) {
                    $metadata['last_modified_by'] = html_entity_decode($m[1]);
                }
            }

            $zip->close();
        }

        return $metadata;
    }

    /**
     * Create a standard GD placeholder preview image for a PowerPoint slide (16:9 canvas).
     */
    private function createPptxSlidePlaceholder(string $outputPath, int $slideNumber, int $totalSlides, string $fileName): void
    {
        $width = 800;
        $height = 450; // 16:9 widescreen presentation ratio

        $im = imagecreatetruecolor($width, $height);
        if ($im === false) {
            return;
        }

        // Colors
        $white = imagecolorallocate($im, 255, 255, 255);
        $bgGrey = imagecolorallocate($im, 248, 250, 252);
        $borderColor = imagecolorallocate($im, 226, 232, 240);
        $primary = imagecolorallocate($im, 234, 88, 12); // Orange #EA580C (PowerPoint Orange)
        $textDark = imagecolorallocate($im, 30, 41, 59);
        $textMuted = imagecolorallocate($im, 100, 116, 139);
        $boxBg = imagecolorallocate($im, 255, 247, 237); // Light orange

        // Background & Slide simulation
        imagefilledrectangle($im, 0, 0, $width, $height, $bgGrey);
        imagefilledrectangle($im, 15, 15, $width - 15, $height - 15, $white);
        imagerectangle($im, 15, 15, $width - 15, $height - 15, $borderColor);

        // Header banner
        imagefilledrectangle($im, 15, 15, $width - 15, 55, $primary);
        $headerText = 'POWERPOINT PRESENTATION PREVIEW';
        imagestring($im, 5, (int) (($width - (strlen($headerText) * 9)) / 2), 26, $headerText, $white);

        // Slide badge box
        imagefilledrectangle($im, 260, 90, $width - 260, 150, $boxBg);
        imagerectangle($im, 260, 90, $width - 260, 150, $primary);
        $badgeText = sprintf('SLIDE %d OF %d', $slideNumber, $totalSlides);
        imagestring($im, 5, (int) (($width - (strlen($badgeText) * 9)) / 2), 112, $badgeText, $primary);

        // Mock slide layout cards
        $cardBorder = imagecolorallocate($im, 226, 232, 240);
        $cardBg = imagecolorallocate($im, 241, 245, 249);
        imagefilledrectangle($im, 50, 180, 360, 340, $cardBg);
        imagerectangle($im, 50, 180, 360, 340, $cardBorder);
        imagefilledrectangle($im, 440, 180, 750, 340, $cardBg);
        imagerectangle($im, 440, 180, 750, 340, $cardBorder);

        // Mock slide lines
        $lineColor = imagecolorallocate($im, 203, 213, 225);
        imagefilledrectangle($im, 70, 210, 340, 220, $lineColor);
        imagefilledrectangle($im, 70, 240, 300, 248, $lineColor);
        imagefilledrectangle($im, 70, 268, 280, 276, $lineColor);

        imagefilledrectangle($im, 460, 210, 730, 220, $lineColor);
        imagefilledrectangle($im, 460, 240, 690, 248, $lineColor);
        imagefilledrectangle($im, 460, 268, 670, 276, $lineColor);

        // File metadata footer info
        $shortName = strlen($fileName) > 40 ? substr($fileName, 0, 37) . '...' : $fileName;
        $infoText = "File: {$shortName}";
        imagestring($im, 3, (int) (($width - (strlen($infoText) * 7)) / 2), $height - 70, $infoText, $textDark);

        $noticeText = '16:9 Presentation Preview';
        imagestring($im, 2, (int) (($width - (strlen($noticeText) * 6)) / 2), $height - 45, $noticeText, $textMuted);

        imagepng($im, $outputPath, 8);
        imagedestroy($im);
    }
}
