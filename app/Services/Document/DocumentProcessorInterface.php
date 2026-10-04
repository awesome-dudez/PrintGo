<?php

declare(strict_types=1);

namespace App\Services\Document;

interface DocumentProcessorInterface
{
    /**
     * Determine if this processor can handle the given file extension and mime type.
     *
     * @param string $extension The file extension (e.g. 'pdf', 'docx', 'jpg')
     * @param string $mimeType The detected MIME type
     * @return bool
     */
    public function canProcess(string $extension, string $mimeType): bool;

    /**
     * Get the total page or slide count of the document.
     *
     * @param string $filePath Absolute path to the source file
     * @return int Total number of pages / slides (minimum 1)
     */
    public function getPageCount(string $filePath): int;

    /**
     * Generate preview images for the document and save them to the output directory.
     *
     * @param string $filePath Absolute path to the source file
     * @param string $outputDir Directory where generated preview images will be stored
     * @param array<string, mixed> $options Processing options (e.g. adjustments, max pages)
     * @return array<int, string> Array of preview file paths (or storage-relative paths)
     */
    public function generatePreviews(string $filePath, string $outputDir, array $options = []): array;

    /**
     * Extract metadata from the document file.
     *
     * @param string $filePath Absolute path to the source file
     * @return array<string, mixed> Key-value dictionary of metadata
     */
    public function getMetadata(string $filePath): array;
}
