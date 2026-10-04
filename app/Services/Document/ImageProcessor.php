<?php

declare(strict_types=1);

namespace App\Services\Document;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ImageProcessor implements DocumentProcessorInterface
{
    /**
     * Determine if this processor can handle the given file extension and mime type.
     */
    public function canProcess(string $extension, string $mimeType): bool
    {
        $ext = strtolower(ltrim($extension, '.'));
        $mime = strtolower($mimeType);

        $validExtensions = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'gif'];
        $validMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/bmp', 'image/gif'];

        return in_array($ext, $validExtensions, true) || in_array($mime, $validMimes, true);
    }

    /**
     * Get the total page count for images (always 1).
     */
    public function getPageCount(string $filePath): int
    {
        return 1;
    }

    /**
     * Generate preview image applying any user adjustments (brightness, contrast, grayscale, rotation).
     *
     * @param string $filePath Absolute path to source image
     * @param string $outputDir Directory where generated preview images will be stored
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    public function generatePreviews(string $filePath, string $outputDir, array $options = []): array
    {
        File::ensureDirectoryExists($outputDir);

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }

        $image = $this->loadImageResource($filePath);
        if ($image === false) {
            Log::warning('ImageProcessor could not load image resource for preview generation', ['file' => $filePath]);
            return [];
        }

        // Apply adjustments if requested
        $adjustments = $options['adjustments'] ?? $options;

        // 1. Rotation
        if (!empty($adjustments['rotation'])) {
            $angle = (int) $adjustments['rotation'];
            if ($angle !== 0) {
                // GD rotates counter-clockwise for positive degrees
                $rotated = imagerotate($image, 360 - ($angle % 360), 0);
                if ($rotated !== false) {
                    imagedestroy($image);
                    $image = $rotated;
                }
            }
        }

        // 2. Grayscale / Black & White
        $isBw = !empty($adjustments['grayscale'])
            || !empty($adjustments['black_and_white'])
            || ($adjustments['color_mode'] ?? null) === 'bw';

        if ($isBw) {
            imagefilter($image, IMG_FILTER_GRAYSCALE);
        }

        // 3. Brightness (-100 to 100)
        if (isset($adjustments['brightness']) && is_numeric($adjustments['brightness'])) {
            $brightness = max(-255, min(255, (int) round(((float) $adjustments['brightness']) * 2.55)));
            if ($brightness !== 0) {
                imagefilter($image, IMG_FILTER_BRIGHTNESS, $brightness);
            }
        }

        // 4. Contrast (-100 to 100)
        if (isset($adjustments['contrast']) && is_numeric($adjustments['contrast'])) {
            // GD contrast: positive means less contrast, negative means more contrast
            $contrast = max(-100, min(100, (int) $adjustments['contrast']));
            if ($contrast !== 0) {
                imagefilter($image, IMG_FILTER_CONTRAST, -$contrast);
            }
        }

        // 5. Scaling (ensure preview does not exceed standard max bounds, e.g. 1600x1600)
        $origWidth = imagesx($image);
        $origHeight = imagesy($image);
        $maxDim = (int) ($options['max_dimension'] ?? 1600);

        if ($origWidth > $maxDim || $origHeight > $maxDim) {
            $ratio = min($maxDim / $origWidth, $maxDim / $origHeight);
            $newWidth = (int) round($origWidth * $ratio);
            $newHeight = (int) round($origHeight * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            // Maintain transparency for PNG
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            imagedestroy($image);
            $image = $resized;
        }

        $targetFileName = 'page_1.png';
        $targetPath = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $targetFileName;

        // Save as PNG
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagepng($image, $targetPath, 8);
        imagedestroy($image);

        return [$targetFileName];
    }

    /**
     * Extract metadata from the image file.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(string $filePath): array
    {
        $metadata = [
            'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
            'page_count' => 1,
        ];

        if (file_exists($filePath)) {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo !== false) {
                $metadata['width'] = $imageInfo[0];
                $metadata['height'] = $imageInfo[1];
                $metadata['mime_type'] = $imageInfo['mime'] ?? 'image/jpeg';
                $metadata['bits'] = $imageInfo['bits'] ?? null;
                $metadata['channels'] = $imageInfo['channels'] ?? null;
            }

            // Read EXIF orientation if JPEG
            if (function_exists('exif_read_data') && ($metadata['mime_type'] ?? '') === 'image/jpeg') {
                try {
                    $exif = @exif_read_data($filePath);
                    if (is_array($exif) && isset($exif['Orientation'])) {
                        $metadata['orientation'] = $exif['Orientation'];
                    }
                } catch (\Throwable $e) {
                    // ignore exif errors
                }
            }
        }

        return $metadata;
    }

    /**
     * Load GD image resource from file path based on its mime/type.
     *
     * @return \GdImage|false
     */
    private function loadImageResource(string $filePath): mixed
    {
        $info = @getimagesize($filePath);
        $mime = $info['mime'] ?? '';

        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png' => @imagecreatefrompng($filePath),
            'image/webp' => @imagecreatefromwebp($filePath),
            'image/bmp' => @imagecreatefrombmp($filePath),
            'image/gif' => @imagecreatefromgif($filePath),
            default => @imagecreatefromstring((string) file_get_contents($filePath)),
        };
    }
}
