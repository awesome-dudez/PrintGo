<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LocalPrintAgentAdapter implements PrinterInterface
{
    /**
     * Cache key for local printer agent hardware state.
     */
    public const CACHE_KEY_HARDWARE_STATUS = 'printer_hardware_status';

    /**
     * Cache prefix for local agent job queue payloads.
     */
    public const CACHE_PREFIX_QUEUE = 'local_agent_job_';

    /**
     * Get the current status of the printer.
     */
    public function getStatus(): PrinterStatusDto
    {
        $printer = Printer::query()->where('is_active', true)->first();

        // Check if there is an active printing job
        $activeJob = PrintJob::query()
            ->whereIn('status', ['printing', 'processing'])
            ->latest('updated_at')
            ->first();

        if ($printer !== null) {
            $status = $printer->status;
            if ($activeJob !== null && $status !== 'error') {
                $status = 'busy';
            }

            return new PrinterStatusDto(
                status: $status,
                ready: (bool) $printer->is_ready && $status !== 'error' && $status !== 'offline',
                error: $printer->error_message,
                paper: [
                    'status' => $printer->paper_status ?? 'available',
                    'level' => 90,
                    'supported_sizes' => ['A4', 'Letter', 'Legal'],
                    'supported_types' => ['bond', 'glossy', 'matte', 'photo'],
                ],
                ink: is_array($printer->ink_status) ? $printer->ink_status : [
                    'black' => 85,
                    'cyan' => 80,
                    'magenta' => 75,
                    'yellow' => 90,
                ],
                currentJob: $activeJob ? (string) $activeJob->id : $printer->current_job_id,
                lastCheckedAt: now()->toIso8601String()
            );
        }

        // Check cached fallback status
        $cached = Cache::get(self::CACHE_KEY_HARDWARE_STATUS, [
            'status' => 'online',
            'ready' => true,
            'error' => null,
            'paper' => ['status' => 'available', 'level' => 85],
            'ink' => ['level' => 75, 'black' => 80, 'cyan' => 75, 'magenta' => 70, 'yellow' => 85],
            'currentJob' => $activeJob ? (string) $activeJob->id : null,
        ]);

        if ($activeJob !== null && ($cached['status'] ?? '') !== 'error') {
            $cached['status'] = 'busy';
            $cached['currentJob'] = (string) $activeJob->id;
        }

        return PrinterStatusDto::fromArray($cached);
    }

    /**
     * Get hardware and media capabilities supported by the active printer.
     *
     * @return array<string, mixed>
     */
    public function getCapabilities(): array
    {
        $printer = Printer::query()->where('is_active', true)->with('capabilities')->first();

        $defaultCapabilities = [
            'paper_sizes' => ['A4', 'Letter', 'Legal'],
            'paper_types' => ['bond', 'glossy', 'matte', 'photo'],
            'color_modes' => ['bw', 'color'],
            'duplex' => true,
            'duplex_supported' => true,
            'orientations' => ['portrait', 'landscape'],
            'resolutions' => ['300dpi', '600dpi', '1200dpi'],
            'max_copies' => 100,
        ];

        if ($printer !== null && $printer->capabilities->isNotEmpty()) {
            $caps = $printer->capabilities->where('is_supported', true)->pluck('capability')->toArray();
            return array_merge($defaultCapabilities, [
                'printer_id' => $printer->id,
                'printer_name' => $printer->name,
                'printer_model' => $printer->model,
                'supported_features' => $caps,
            ]);
        }

        return $defaultCapabilities;
    }

    /**
     * Submit a print job to the local print agent queue.
     */
    public function submitPrintJob(PrintJob $job): PrintResultDto
    {
        $printer = Printer::query()->where('is_active', true)->first();

        // Check if printer is in error or offline
        if ($printer !== null && (!$printer->is_ready || $printer->status === 'error' || $printer->status === 'offline')) {
            $error = $printer->error_message ?? 'Printer is currently offline or in an error state';
            $job->update([
                'status' => 'failed',
                'error_message' => $error,
            ]);

            return PrintResultDto::failure((string) $job->id, $error);
        }

        try {
            $now = now();

            // 1. Update PrintJob record
            $job->update([
                'status' => 'queued',
                'printer_id' => $printer?->id,
                'queued_at' => $now,
                'error_message' => null,
            ]);

            // 2. Write queue dispatch payload for local print agent
            $queuePayload = [
                'job_id' => (string) $job->id,
                'session_id' => (string) $job->session_id,
                'file_id' => (string) $job->file_id,
                'copies' => $job->copies,
                'paper_size' => $job->paper_size,
                'paper_type' => $job->paper_type,
                'orientation' => $job->orientation,
                'color_mode' => $job->color_mode,
                'duplex' => (bool) $job->duplex,
                'page_count' => $job->page_count,
                'adjustments' => $job->adjustments,
                'queued_at' => $now->toIso8601String(),
            ];

            Cache::put(self::CACHE_PREFIX_QUEUE . $job->id, $queuePayload, 86400);

            // 3. Mark printer current job
            if ($printer !== null) {
                $printer->update([
                    'current_job_id' => (string) $job->id,
                    'status' => 'busy',
                ]);
            }

            Log::info('Print job submitted to local print agent queue', [
                'job_id' => $job->id,
                'paper_size' => $job->paper_size,
                'color_mode' => $job->color_mode,
            ]);

            return PrintResultDto::success((string) $job->id, 'Print job queued for local print agent execution');
        } catch (\Throwable $e) {
            Log::error('Failed to submit print job to local agent queue', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return PrintResultDto::failure((string) $job->id, $e->getMessage());
        }
    }

    /**
     * Cancel an active or queued print job.
     */
    public function cancelPrintJob(string $jobId): bool
    {
        $job = PrintJob::query()->find($jobId);
        if ($job === null) {
            return false;
        }

        if (in_array($job->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        $job->update([
            'status' => 'cancelled',
            'error_message' => 'Job cancelled by user request',
        ]);

        Cache::forget(self::CACHE_PREFIX_QUEUE . $jobId);

        $printer = Printer::query()->where('current_job_id', $jobId)->first();
        if ($printer !== null) {
            $printer->update([
                'current_job_id' => null,
                'status' => 'online',
            ]);
        }

        return true;
    }

    /**
     * Get status and progress information of a specific print job.
     *
     * @return array<string, mixed>
     */
    public function getPrintJobStatus(string $jobId): array
    {
        $job = PrintJob::query()->find($jobId);

        if ($job === null) {
            return [
                'found' => false,
                'job_id' => $jobId,
                'status' => 'unknown',
            ];
        }

        return [
            'found' => true,
            'job_id' => (string) $job->id,
            'session_id' => (string) $job->session_id,
            'status' => $job->status,
            'progress' => $job->progress,
            'page_count' => $job->page_count,
            'pages_completed' => $job->pages_completed,
            'copies' => $job->copies,
            'paper_size' => $job->paper_size,
            'color_mode' => $job->color_mode,
            'error_message' => $job->error_message,
            'queued_at' => $job->queued_at?->toIso8601String(),
            'started_at' => $job->started_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
        ];
    }
}
