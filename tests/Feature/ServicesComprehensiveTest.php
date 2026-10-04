<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\UnsupportedFileTypeException;
use App\Jobs\CleanupCompletedJobFilesJob;
use App\Jobs\CleanupExpiredSessionsJob;
use App\Jobs\ExecutePrintJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\PrintingSession;
use App\Models\Transaction;
use App\Models\UploadedFile;
use App\Services\Document\DocumentProcessorFactory;
use App\Services\Document\DocxProcessor;
use App\Services\Document\HeicProcessor;
use App\Services\Document\ImageProcessor;
use App\Services\Document\PdfProcessor;
use App\Services\Document\PptxProcessor;
use App\Services\Payment\PaymentService;
use App\Services\Pricing\PricingService;
use App\Services\Printing\LocalPrintAgentAdapter;
use App\Services\Printing\PrinterInterface;
use App\Services\Printing\PrinterStatusDto;
use App\Services\Printing\PrinterStatusService;
use App\Services\Printing\PrintResultDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServicesComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_services_' . uniqid();
        File::ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_pricing_service_complete_matrix_and_breakdown(): void
    {
        $pricing = app(PricingService::class);

        $calc = $pricing->calculatePrice(
            pages: 5,
            copies: 2,
            paperSize: 'Legal',
            paperType: 'photo',
            colorMode: 'color',
            duplex: true
        );

        $this->assertSame(5, $calc['pages']);
        $this->assertSame(2, $calc['copies']);
        $this->assertSame('Legal', $calc['paper_size']);
        $this->assertSame('photo', $calc['paper_type']);
        $this->assertSame('color', $calc['color_mode']);
        $this->assertTrue($calc['duplex']);
        $this->assertIsArray($calc['breakdown']);
        $this->assertArrayHasKey('base_per_page', $calc['breakdown']);
        $this->assertArrayHasKey('paper_type_surcharge', $calc['breakdown']);
        $this->assertArrayHasKey('paper_size_surcharge', $calc['breakdown']);
        $this->assertArrayHasKey('per_copy_subtotal', $calc['breakdown']);
        $this->assertArrayHasKey('subtotal', $calc['breakdown']);
        $this->assertGreaterThan(0, $calc['total']);
        $this->assertSame('PHP', $calc['currency']);
    }

    public function test_payment_service_handles_multiple_increments(): void
    {
        $paymentService = app(PaymentService::class);

        $session = PrintingSession::create([
            'id' => (string) Str::uuid(),
            'status' => 'active',
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $paymentService->createOrUpdatePayment($session->id, 10.00);

        $p1 = $paymentService->recordPayment($session->id, 2.00);
        $this->assertSame('partial', $p1->status);
        $this->assertSame(2.00, (float) $p1->inserted_amount);
        $this->assertSame(8.00, (float) $p1->remaining_amount);

        $p2 = $paymentService->recordPayment($session->id, 3.00);
        $this->assertSame('partial', $p2->status);
        $this->assertSame(5.00, (float) $p2->inserted_amount);
        $this->assertSame(5.00, (float) $p2->remaining_amount);

        $p3 = $paymentService->recordPayment($session->id, 5.00);
        $this->assertSame('paid', $p3->status);
        $this->assertSame(10.00, (float) $p3->inserted_amount);
        $this->assertSame(0.00, (float) $p3->remaining_amount);
        $this->assertTrue($paymentService->isPaid($session->id));
    }

    public function test_printer_status_service_updates_hardware_status(): void
    {
        $statusService = app(PrinterStatusService::class);

        Printer::create([
            'name' => 'Kiosk Printer 1',
            'model' => 'L805',
            'connection_type' => 'agent',
            'status' => 'online',
            'is_ready' => true,
            'paper_status' => 'available',
            'ink_status' => ['black' => 95],
            'is_active' => true,
        ]);

        $statusService->updateStatus([
            'paper_status' => 'low',
            'ink_status' => ['black' => 20],
        ]);

        $status = $statusService->getStatus();
        $this->assertSame('low', $status->paper['status']);
        $this->assertSame(20, $status->ink['black']);
    }

    public function test_process_document_job_marks_file_failed_on_invalid_file(): void
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
            'original_name' => 'corrupted.unknown_ext',
            'stored_path' => 'uploads/non_existent.bin',
            'file_type' => 'unknown',
            'mime_type' => 'application/octet-stream',
            'file_size' => 10,
            'page_count' => 0,
            'status' => 'pending',
            'preview_available' => false,
        ]);

        $factory = app(DocumentProcessorFactory::class);
        $job = new ProcessDocumentJob($file);
        $job->handle($factory);

        $file->refresh();
        $this->assertSame('failed', $file->status);
        $this->assertNotNull($file->error_message);
    }

    public function test_execute_print_job_fails_when_printer_is_in_error_state(): void
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
            'file_size' => 100,
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

        // Create printer in error state
        Printer::create([
            'name' => 'Faulty Printer',
            'model' => 'L3110',
            'connection_type' => 'agent',
            'status' => 'error',
            'is_ready' => false,
            'error_message' => 'Paper jam in tray 1',
            'is_active' => true,
        ]);

        $paymentService = app(PaymentService::class);
        $paymentService->createOrUpdatePayment($session->id, 2.00);
        $paymentService->recordPayment($session->id, 2.00);

        $printer = app(PrinterInterface::class);
        $job = new ExecutePrintJob($printJob);
        $job->handle($printer, $paymentService);

        $printJob->refresh();
        $this->assertSame('failed', $printJob->status);
        $this->assertStringContainsString('Paper jam', $printJob->error_message);
    }
}
