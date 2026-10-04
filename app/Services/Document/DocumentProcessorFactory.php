<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Exceptions\UnsupportedFileTypeException;

class DocumentProcessorFactory
{
    /**
     * @var list<DocumentProcessorInterface>
     */
    private array $processors = [];

    /**
     * Create a new DocumentProcessorFactory and register default processors.
     *
     * @param list<DocumentProcessorInterface> $processors
     */
    public function __construct(array $processors = [])
    {
        if (!empty($processors)) {
            $this->processors = $processors;
        } else {
            $this->registerDefaultProcessors();
        }
    }

    /**
     * Register the default suite of document processors.
     */
    public function registerDefaultProcessors(): void
    {
        $this->processors = [
            new PdfProcessor(),
            new ImageProcessor(),
            new DocxProcessor(),
            new PptxProcessor(),
            new HeicProcessor(),
        ];
    }

    /**
     * Register an additional document processor.
     */
    public function register(DocumentProcessorInterface $processor): void
    {
        $this->processors[] = $processor;
    }

    /**
     * Get the processor for the given file extension and mime type.
     *
     * @throws UnsupportedFileTypeException
     */
    public function getProcessor(string $extension, string $mimeType): DocumentProcessorInterface
    {
        $processor = $this->findProcessor($extension, $mimeType);

        if ($processor === null) {
            throw new UnsupportedFileTypeException(
                sprintf('Unsupported file type: extension "%s", MIME "%s"', $extension, $mimeType)
            );
        }

        return $processor;
    }

    /**
     * Find a matching processor or return null if none is supported.
     */
    public function findProcessor(string $extension, string $mimeType): ?DocumentProcessorInterface
    {
        foreach ($this->processors as $processor) {
            if ($processor->canProcess($extension, $mimeType)) {
                return $processor;
            }
        }

        return null;
    }

    /**
     * Find and return the processor matching a physical file path.
     *
     * @throws UnsupportedFileTypeException
     */
    public function getProcessorForFile(string $filePath, ?string $originalName = null, ?string $mimeType = null): DocumentProcessorInterface
    {
        $name = $originalName ?? basename($filePath);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($mimeType === null && file_exists($filePath)) {
            $mimeType = (string) (@mime_content_type($filePath) ?: 'application/octet-stream');
        }

        return $this->getProcessor($extension, $mimeType ?? 'application/octet-stream');
    }

    /**
     * Check if a file extension and mime type is supported.
     */
    public function isSupported(string $extension, string $mimeType): bool
    {
        return $this->findProcessor($extension, $mimeType) !== null;
    }

    /**
     * Get list of all registered processors.
     *
     * @return list<DocumentProcessorInterface>
     */
    public function getAllProcessors(): array
    {
        return $this->processors;
    }

    /**
     * Get common supported extensions.
     *
     * @return list<string>
     */
    public function getSupportedExtensions(): array
    {
        return ['pdf', 'docx', 'doc', 'pptx', 'ppt', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
    }
}
