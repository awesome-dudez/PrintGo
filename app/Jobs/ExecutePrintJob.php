<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PrintJob;
use App\Services\Payment\PaymentService;
use App\Services\Printing\PrinterInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecutePrintJob implements ShouldQueue
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
    public int $timeout = 180;

    public function __construct(
        public PrintJob $printJob
    ) {}

    /**
     * Execute the print job.
     */
    public function handle(PrinterInterface $printer, PaymentService $paymentService): void
    {
        $job = $this->printJob;

        Log::info('Initiating print execution for job', ['job_id' => $job->id, 'session_id' => $job->session_id]);

        // 1. Verify payment status before proceeding with physical printing
        $isPaid = $paymentService->isPaid((string) $job->session_id);
        if (!$isPaid) {
            $errorMessage = 'Cannot execute print job: payment has not been completed for this session.';
            Log::warning($errorMessage, ['job_id' => $job->id, 'session_id' => $job->session_id]);

            $job->update([
                'status' => 'failed',
                'payment_status' => 'unpaid',
                'error_message' => $errorMessage,
            ]);

            return;
        }

        // 2. Transition status to printing
        $job->update([
            'status' => 'printing',
            'payment_status' => 'paid',
            'started_at' => now(),
            'error_message' => null,
        ]);

        // 3. Dispatch to printer adapter
        $result = $printer->submitPrintJob($job);

        if (!$result->success) {
            $error = $result->error ?? 'Print submission rejected by printer service.';
            Log::error('Print job execution failed', [
                'job_id' => $job->id,
                'error' => $error,
            ]);

            $job->update([
                'status' => 'failed',
                'error_message' => $error,
            ]);

            return;
        }

        Log::info('Print job successfully queued to printer interface', [
            'job_id' => $job->id,
            'message' => $result->message,
        ]);
    }
}
