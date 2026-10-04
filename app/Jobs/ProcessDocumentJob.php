<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\UploadedFile;
use App\Services\Document\DocumentProcessorFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 120;

    public function __construct(
        public UploadedFile $uploadedFile
    ) {}

    /**
     * Execute the document processing job.
     */
    public function handle(DocumentProcessorFactory $factory): void
    {
        $file = $this->uploadedFile;

        try {
            $file->update([
                'status' => 'processing',
                'error_message' => null,
            ]);

            // Resolve physical file path on local storage disk
            $disk = Storage::disk('local');
            $filePath = $disk->path($file->stored_path);

            if (!file_exists($filePath)) {
                // Fallback check if stored_path is already absolute or on public disk
                if (file_exists($file->stored_path)) {
                    $filePath = $file->stored_path;
                } else {
                    throw new \RuntimeException(sprintf('Uploaded file not found on disk at: %s', $file->stored_path));
                }
            }

            // Obtain suitable processor
            $processor = $factory->getProcessorForFile(
                filePath: $filePath,
                originalName: $file->original_name,
                mimeType: $file->mime_type
            );

            // 1. Extract total page / slide count
            $pageCount = $processor->getPageCount($filePath);

            // 2. Prepare preview destination directory on public storage
            $previewRelativeDir = "previews/{$file->id}";
            $previewAbsoluteDir = Storage::disk('public')->path($previewRelativeDir);
            File::ensureDirectoryExists($previewAbsoluteDir);

            // 3. Generate preview images
            $generatedFiles = $processor->generatePreviews(
                filePath: $filePath,
                outputDir: $previewAbsoluteDir,
                options: ['max_pages' => 30]
            );

            // Map preview files to storage relative paths
            $previewPaths = [];
            foreach ($generatedFiles as $genFile) {
                $previewPaths[] = "{$previewRelativeDir}/" . basename($genFile);
            }

            // 4. Update file record
            $file->update([
                'page_count' => max(1, $pageCount),
                'preview_paths' => $previewPaths,
                'preview_available' => !empty($previewPaths),
                'status' => 'ready',
                'error_message' => null,
            ]);

            Log::info('Document processed successfully', [
                'file_id' => $file->id,
                'page_count' => $pageCount,
                'preview_count' => count($previewPaths),
            ]);
        } catch (\Throwable $e) {
            Log::error('Document processing failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $file->update([
                'status' => 'failed',
                'preview_available' => false,
                'error_message' => $e->getMessage(),
            ]);

            // Re-throw if within attempts to let queue handle or fail
            if ($this->attempts() >= $this->tries) {
                $this->fail($e);
            }
        }
    }
}
