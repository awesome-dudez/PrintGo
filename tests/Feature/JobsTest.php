<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CleanupCompletedJobFilesJob;
use App\Jobs\CleanupExpiredSessionsJob;
use App\Jobs\ExecutePrintJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\PrintingSession;
use App\Models\Transaction;
use App\Models\UploadedFile;
use App\Services\Document\DocumentProcessorFactory;
use App\Services\Payment\PaymentService;
use App\Services\Printing\PrinterInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class JobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_process_document_job_processes_image_file(): void
    {
        $session = PrintingSession::create([
            'id' => (string) Str::uuid(),
            'status' => 'active',
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        // Create a 100x100 fake png image on storage disk
        $im = imagecreatetruecolor(100, 100);
        ob_start();
        imagepng($im);
        $content = ob_get_clean();
        imagedestroy($im);

        $storedPath = 'uploads/test_photo.png';
        Storage::disk('local')->put($storedPath, $content);

        $file = UploadedFile::create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'original_name' => 'test_photo.png',
            'stored_path' => $storedPath,
            'file_type' => 'png',
            'mime_type' => 'image/png',
            'file_size' => strlen((string) $content),
            'page_count' => 0,
            'status' => 'pending',
            'preview_available' => false,
        ]);

        $factory = new DocumentProcessorFactory();
        $job = new ProcessDocumentJob($file);
        $job->handle($factory);

        $file->refresh();
        $this->assertSame('ready', $file->status);
        $this->assertSame(1, $file->page_count);
        $this->assertTrue($file->preview_available);
        $this->assertNotEmpty($file->preview_paths);
    }

    public function test_execute_print_job_verifies_payment_and_dispatches(): void
    {
        $session = PrintingSession::create([
            'id' => (string) Str::uuid(),
            'status' => 'active',
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $file = UploadedFile::create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'original_name' => 'doc.pdf',
            'stored_path' => 'uploads/doc.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'page_count' => 1,
            'status' => 'ready',
        ]);

        $printJob = PrintJob::create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'file_id' => $file->id,
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'color_mode' => 'bw',
            'page_count' => 1,
            'total_price' => 2.00,
            'status' => 'pending',
        ]);

        $printer = app(PrinterInterface::class);
        $paymentService = app(PaymentService::class);

        // Case 1: Unpaid job fails
        $job = new ExecutePrintJob($printJob);
        $job->handle($printer, $paymentService);

        $printJob->refresh();
        $this->assertSame('failed', $printJob->status);

        // Case 2: Mark payment as paid and retry
        $paymentService->createOrUpdatePayment($session->id, 2.00);
        $paymentService->recordPayment($session->id, 2.00, 'coin');

        $job = new ExecutePrintJob($printJob);
        $job->handle($printer, $paymentService);

        $printJob->refresh();
        $this->assertSame('queued', $printJob->status);
    }

    public function test_cleanup_expired_sessions_job_purges_unprinted_files(): void
    {
        $expiredSession = PrintingSession::create([
            'id' => (string) Str::uuid(),
            'status' => 'active',
            'last_activity_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);

        $storedPath = 'uploads/expired_file.pdf';
        Storage::disk('local')->put($storedPath, 'DUMMY_CONTENT');

        $file = UploadedFile::create([
            'id' => (string) Str::uuid(),
            'session_id' => $expiredSession->id,
            'original_name' => 'expired_file.pdf',
            'stored_path' => $storedPath,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'page_count' => 1,
            'status' => 'ready',
            'preview_available' => true,
        ]);

        $cleanupJob = new CleanupExpiredSessionsJob(sessionTtlMinutes: 30);
        $cleanupJob->handle();

        $expiredSession->refresh();
        $file->refresh();

        $this->assertSame('expired', $expiredSession->status);
        $this->assertSame('expired', $file->status);
        $this->assertFalse(Storage::disk('local')->exists($storedPath));
    }

    public function test_cleanup_completed_job_files_purges_temporary_storage(): void
    {
        $session = PrintingSession::create([
            'id' => (string) Str::uuid(),
            'status' => 'active',
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $storedPath = 'uploads/completed_print.pdf';
        Storage::disk('local')->put($storedPath, 'COMPLETED_CONTENT');

        $file = UploadedFile::create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'original_name' => 'completed_print.pdf',
            'stored_path' => $storedPath,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 200,
            'page_count' => 1,
            'status' => 'ready',
            'preview_available' => true,
        ]);

        $printJob = PrintJob::create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'file_id' => $file->id,
            'status' => 'completed',
            'total_price' => 5.00,
        ]);

        Transaction::create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'print_job_id' => $printJob->id,
            'amount' => 5.00,
            'currency' => 'PHP',
            'payment_status' => 'paid',
            'print_status' => 'completed',
            'file_type' => 'pdf',
            'page_count' => 1,
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
        ]);

        $cleanupJob = new CleanupCompletedJobFilesJob();
        $cleanupJob->handle();

        $file->refresh();
        $this->assertSame('deleted', $file->status);
        $this->assertFalse(Storage::disk('local')->exists($storedPath));
    }
}
