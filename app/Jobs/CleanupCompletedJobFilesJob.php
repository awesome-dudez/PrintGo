<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PrintJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupCompletedJobFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the completed job files cleanup.
     */
    public function handle(): void
    {
        // Find completed print jobs with associated files
        $completedJobs = PrintJob::query()
            ->where('status', 'completed')
            ->with(['file', 'transaction'])
            ->get();

        $filesPurged = 0;

        foreach ($completedJobs as $job) {
            $file = $job->file;

            if ($file === null || $file->status === 'deleted') {
                continue;
            }

            // Verify another active or incomplete print job does not rely on this same file
            $hasOtherActiveJobs = PrintJob::query()
                ->where('file_id', $file->id)
                ->where('id', '!=', $job->id)
                ->whereIn('status', ['pending', 'queued', 'printing', 'processing'])
                ->exists();

            if ($hasOtherActiveJobs) {
                continue;
            }

            // 1. Delete source upload from local disk
            if ($file->stored_path && Storage::disk('local')->exists($file->stored_path)) {
                Storage::disk('local')->delete($file->stored_path);
            }

            // 2. Delete preview images from public disk
            $previewDir = "previews/{$file->id}";
            if (Storage::disk('public')->exists($previewDir)) {
                Storage::disk('public')->deleteDirectory($previewDir);
            }

            // 3. Mark file as deleted while preserving transaction and print job records
            $file->update([
                'status' => 'deleted',
                'preview_available' => false,
                'preview_paths' => null,
            ]);

            $filesPurged++;
        }

        Log::info('CleanupCompletedJobFilesJob completed', [
            'files_purged' => $filesPurged,
        ]);
    }
}
