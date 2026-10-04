<?php

declare(strict_types=1);

namespace App\Services\Document;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class DocxProcessor implements DocumentProcessorInterface
{
    /**
     * Determine if this processor can handle the given file extension and mime type.
     */
    public function canProcess(string $extension, string $mimeType): bool
    {
        $ext = strtolower(ltrim($extension, '.'));
        $mime = strtolower($mimeType);

        return $ext === 'docx'
            || $ext === 'doc'
            || str_contains($mime, 'wordprocessingml')
            || str_contains($mime, 'msword')
            || (str_contains($mime, 'zip') && $ext === 'docx');
    }

    /**
     * Get the total page count of the DOCX document.
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

        $pageCount = 1;

        // 1. Try reading docProps/app.xml for <Pages> tag
        $appXml = $zip->getFromName('docProps/app.xml');
        if ($appXml !== false) {
            if (preg_match('/<Pages>(\d+)<\/Pages>/i', $appXml, $matches)) {
                $count = (int) $matches[1];
                if ($count > 0) {
                    $zip->close();
                    return $count;
                }
            }
        }

        // 2. Fallback: inspect word/document.xml for page breaks or paragraphs
        $docXml = $zip->getFromName('word/document.xml');
        if ($docXml !== false) {
            // Count explicitly rendered page breaks
            $breaks = preg_match_all('/<w:lastRenderedPageBreak[^>]*\/>/i', $docXml)
                + preg_match_all('/<w:br[^>]+w:type="page"[^>]*\/>/i', $docXml);

            if ($breaks > 0) {
                $pageCount = $breaks + 1;
            } else {
                // Approximate from paragraph count (average 10-15 paragraphs per standard page)
                $paragraphs = preg_match_all('/<w:p[\s>]/i', $docXml);
                if ($paragraphs > 0) {
                    $pageCount = max(1, (int) ceil($paragraphs / 12));
                }
            }
        }

        $zip->close();

        return max(1, $pageCount);
    }

    /**
     * Generate preview images for DOCX pages.
     *
     * @param string $filePath Absolute path to source DOCX
     * @param string $outputDir Directory where generated preview images will be stored
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    public function generatePreviews(string $filePath, string $outputDir, array $options = []): array
    {
        File::ensureDirectoryExists($outputDir);
        $pageCount = $this->getPageCount($filePath);
        $maxPages = (int) ($options['max_pages'] ?? 30);
        $pagesToRender = min($pageCount, $maxPages);
        $previews = [];
        $filename = basename($filePath);

        for ($i = 1; $i <= $pagesToRender; $i++) {
            $targetFileName = sprintf('page_%d.png', $i);
            $targetPath = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $targetFileName;

            $this->createDocxPagePlaceholder(
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
     * Extract metadata from the DOCX file.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(string $filePath): array
    {
        $metadata = [
            'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
            'page_count' => $this->getPageCount($filePath),
            'extension' => 'docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        if (!file_exists($filePath)) {
            return $metadata;
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            // Read docProps/app.xml
            $appXml = $zip->getFromName('docProps/app.xml');
            if ($appXml !== false) {
                if (preg_match('/<Words>(\d+)<\/Words>/i', $appXml, $m)) {
                    $metadata['words'] = (int) $m[1];
                }
                if (preg_match('/<Characters>(\d+)<\/Characters>/i', $appXml, $m)) {
                    $metadata['characters'] = (int) $m[1];
                }
                if (preg_match('/<Paragraphs>(\d+)<\/Paragraphs>/i', $appXml, $m)) {
                    $metadata['paragraphs'] = (int) $m[1];
                }
                if (preg_match('/<Lines>(\d+)<\/Lines>/i', $appXml, $m)) {
                    $metadata['lines'] = (int) $m[1];
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
                if (preg_match('/<dcterms:created[^>]*>([^<]+)<\/dcterms:created>/i', $coreXml, $m)) {
                    $metadata['created_at'] = $m[1];
                }
            }

            $zip->close();
        }

        return $metadata;
    }

    /**
     * Create a standard GD placeholder preview image for a Word document page.
     */
    private function createDocxPagePlaceholder(string $outputPath, int $pageNumber, int $totalPages, string $fileName): void
    {
        $width = 600;
        $height = 848; // Standard A4 ratio

        $im = imagecreatetruecolor($width, $height);
        if ($im === false) {
            return;
        }

        // Colors
        $white = imagecolorallocate($im, 255, 255, 255);
        $bgGrey = imagecolorallocate($im, 248, 250, 252);
        $borderColor = imagecolorallocate($im, 226, 232, 240);
        $primary = imagecolorallocate($im, 37, 99, 235); // Blue #2563EB (Word Blue)
        $textDark = imagecolorallocate($im, 30, 41, 59);
        $textMuted = imagecolorallocate($im, 100, 116, 139);
        $boxBg = imagecolorallocate($im, 239, 246, 255); // Light blue

        // Background & Page simulation
        imagefilledrectangle($im, 0, 0, $width, $height, $bgGrey);
        imagefilledrectangle($im, 20, 20, $width - 20, $height - 20, $white);
        imagerectangle($im, 20, 20, $width - 20, $height - 20, $borderColor);

        // Header banner
        imagefilledrectangle($im, 20, 20, $width - 20, 70, $primary);
        $headerText = 'WORD DOCUMENT PREVIEW';
        imagestring($im, 5, (int) (($width - (strlen($headerText) * 9)) / 2), 35, $headerText, $white);

        // Page badge box
        imagefilledrectangle($im, 150, 140, $width - 150, 220, $boxBg);
        imagerectangle($im, 150, 140, $width - 150, 220, $primary);
        $badgeText = sprintf('PAGE %d OF %d', $pageNumber, $totalPages);
        imagestring($im, 5, (int) (($width - (strlen($badgeText) * 9)) / 2), 170, $badgeText, $primary);

        // Mock document body lines
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

        $noticeText = 'Standard Page Preview (DOCX Document)';
        imagestring($im, 2, (int) (($width - (strlen($noticeText) * 6)) / 2), $height - 70, $noticeText, $textMuted);

        imagepng($im, $outputPath, 8);
        imagedestroy($im);
    }
}
