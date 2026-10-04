<?php

declare(strict_types=1);

namespace App\Services\Document;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class HeicProcessor implements DocumentProcessorInterface
{
    /**
     * Determine if this processor can handle the given file extension and mime type.
     */
    public function canProcess(string $extension, string $mimeType): bool
    {
        $ext = strtolower(ltrim($extension, '.'));
        $mime = strtolower($mimeType);

        return in_array($ext, ['heic', 'heif'], true)
            || str_contains($mime, 'heic')
            || str_contains($mime, 'heif');
    }

    /**
     * Get the total page count for HEIC images (always 1).
     */
    public function getPageCount(string $filePath): int
    {
        return 1;
    }

    /**
     * Generate preview image for HEIC file (using Imagick if available, or GD placeholder).
     *
     * @param string $filePath Absolute path to source HEIC
     * @param string $outputDir Directory where generated preview images will be stored
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    public function generatePreviews(string $filePath, string $outputDir, array $options = []): array
    {
        File::ensureDirectoryExists($outputDir);
        $targetFileName = 'page_1.png';
        $targetPath = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $targetFileName;

        // Try Imagick if it has HEIC support
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            try {
                $formats = \Imagick::queryFormats('HEIC');
                if (!empty($formats)) {
                    $imagick = new \Imagick($filePath);
                    $imagick->setImageFormat('png');
                    $imagick->writeImage($targetPath);
                    $imagick->clear();
                    $imagick->destroy();
                    return [$targetFileName];
                }
            } catch (\Throwable $e) {
                Log::warning('Imagick HEIC decoding failed, falling back to GD placeholder', [
                    'file' => $filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Standard GD placeholder preview
        $this->createHeicPlaceholder(
            outputPath: $targetPath,
            fileName: basename($filePath),
            fileSize: file_exists($filePath) ? filesize($filePath) : 0
        );

        return [$targetFileName];
    }

    /**
     * Extract metadata from the HEIC file.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(string $filePath): array
    {
        return [
            'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
            'page_count' => 1,
            'extension' => 'heic',
            'mime_type' => 'image/heic',
            'format' => 'High Efficiency Image Container (HEIC)',
        ];
    }

    /**
     * Create a standard GD placeholder preview image for a HEIC image.
     */
    private function createHeicPlaceholder(string $outputPath, string $fileName, int $fileSize): void
    {
        $width = 600;
        $height = 800;

        $im = imagecreatetruecolor($width, $height);
        if ($im === false) {
            return;
        }

        // Colors
        $white = imagecolorallocate($im, 255, 255, 255);
        $bgGrey = imagecolorallocate($im, 248, 250, 252);
        $borderColor = imagecolorallocate($im, 226, 232, 240);
        $primary = imagecolorallocate($im, 13, 148, 136); // Teal #0D9488
        $textDark = imagecolorallocate($im, 30, 41, 59);
        $textMuted = imagecolorallocate($im, 100, 116, 139);
        $boxBg = imagecolorallocate($im, 240, 253, 250); // Light teal

        // Background
        imagefilledrectangle($im, 0, 0, $width, $height, $bgGrey);
        imagefilledrectangle($im, 20, 20, $width - 20, $height - 20, $white);
        imagerectangle($im, 20, 20, $width - 20, $height - 20, $borderColor);

        // Header banner
        imagefilledrectangle($im, 20, 20, $width - 20, 70, $primary);
        $headerText = 'HEIC IMAGE PREVIEW';
        imagestring($im, 5, (int) (($width - (strlen($headerText) * 9)) / 2), 35, $headerText, $white);

        // Image frame simulation
        imagefilledrectangle($im, 80, 140, $width - 80, $height - 200, $boxBg);
        imagerectangle($im, 80, 140, $width - 80, $height - 200, $primary);

        // Center icon / badge
        $badgeText = 'HIGH EFFICIENCY IMAGE';
        imagestring($im, 5, (int) (($width - (strlen($badgeText) * 9)) / 2), 260, $badgeText, $primary);

        $badgeSubtext = '(Apple iOS / HEIF Photo)';
        imagestring($im, 3, (int) (($width - (strlen($badgeSubtext) * 7)) / 2), 300, $badgeSubtext, $textDark);

        $sizeMb = round($fileSize / (1024 * 1024), 2);
        $sizeText = "Original Size: {$sizeMb} MB";
        imagestring($im, 3, (int) (($width - (strlen($sizeText) * 7)) / 2), 340, $sizeText, $textMuted);

        // File metadata footer info
        $shortName = strlen($fileName) > 35 ? substr($fileName, 0, 32) . '...' : $fileName;
        $infoText = "File: {$shortName}";
        imagestring($im, 3, (int) (($width - (strlen($infoText) * 7)) / 2), $height - 120, $infoText, $textDark);

        $noticeText = 'Single Page Photo Document';
        imagestring($im, 2, (int) (($width - (strlen($noticeText) * 6)) / 2), $height - 90, $noticeText, $textMuted);

        imagepng($im, $outputPath, 8);
        imagedestroy($im);
    }
}
