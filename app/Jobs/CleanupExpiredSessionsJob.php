<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PrintingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredSessionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param int $sessionTtlMinutes Default TTL in minutes if expires_at is null
     */
    public function __construct(
        public int $sessionTtlMinutes = 30
    ) {}

    /**
     * Execute the expired sessions cleanup job.
     */
    public function handle(): void
    {
        $now = now();
        $ttlThreshold = $now->copy()->subMinutes($this->sessionTtlMinutes);

        // Find expired or abandoned sessions
        $expiredSessions = PrintingSession::query()
            ->where(function ($query) use ($now, $ttlThreshold) {
                $query->where('expires_at', '<', $now)
                    ->orWhere('status', 'expired')
                    ->orWhere(function ($sub) use ($ttlThreshold) {
                        $sub->whereNull('expires_at')
                            ->where('created_at', '<', $ttlThreshold);
                    });
            })
            ->with(['files', 'printJobs', 'transactions'])
            ->get();

        $cleanedCount = 0;
        $filesDeleted = 0;

        foreach ($expiredSessions as $session) {
            // Do not delete files if session has an active or queued print job
            $hasActivePrintJob = $session->printJobs->contains(function ($job) {
                return in_array($job->status, ['queued', 'printing', 'processing'], true);
            });

            if ($hasActivePrintJob) {
                continue;
            }

            // Do not delete files if there is a completed transaction with pending print
            $hasCompletedTransaction = $session->transactions->contains(function ($txn) {
                return $txn->status === 'completed' || $txn->payment_status === 'paid';
            });

            // If session is expired and has no active jobs, purge temporary files
            foreach ($session->files as $file) {
                // Delete stored physical file
                if ($file->stored_path && Storage::disk('local')->exists($file->stored_path)) {
                    Storage::disk('local')->delete($file->stored_path);
                    $filesDeleted++;
                }

                // Delete preview directory
                $previewDir = "previews/{$file->id}";
                if (Storage::disk('public')->exists($previewDir)) {
                    Storage::disk('public')->deleteDirectory($previewDir);
                }

                $file->update([
                    'status' => 'expired',
                    'preview_available' => false,
                    'preview_paths' => null,
                ]);
            }

            $session->update([
                'status' => 'expired',
            ]);

            $cleanedCount++;
        }

        Log::info('CleanupExpiredSessionsJob completed', [
            'sessions_cleaned' => $cleanedCount,
            'files_purged' => $filesDeleted,
        ]);
    }
}
