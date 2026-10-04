<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\PrintingSession;
use App\Models\UploadedFile;
use App\Services\Printing\LocalPrintAgentAdapter;
use App\Services\Printing\PrinterStatusDto;
use App\Services\Printing\PrinterStatusService;
use App\Services\Printing\PrintResultDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrinterAbstractionTest extends TestCase
{
    use RefreshDatabase;

    private LocalPrintAgentAdapter $adapter;
    private PrinterStatusService $statusService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new LocalPrintAgentAdapter();
        $this->statusService = new PrinterStatusService($this->adapter);
    }

    public function test_printer_status_dto_structure(): void
    {
        $dto = new PrinterStatusDto(
            status: 'online',
            ready: true,
            error: null,
            paper: ['level' => 80],
            ink: ['black' => 90],
            currentJob: null
        );

        $this->assertSame('online', $dto->status);
        $this->assertTrue($dto->ready);
        $array = $dto->toArray();
        $this->assertSame('online', $array['status']);
        $this->assertTrue($array['ready']);
    }

    public function test_local_agent_adapter_status_and_capabilities(): void
    {
        $printer = Printer::create([
            'name' => 'Epson L3110 Test',
            'model' => 'L3110',
            'connection_type' => 'agent',
            'status' => 'online',
            'is_ready' => true,
            'is_active' => true,
        ]);

        $status = $this->adapter->getStatus();
        $this->assertSame('online', $status->status);
        $this->assertTrue($status->ready);

        $caps = $this->adapter->getCapabilities();
        $this->assertContains('A4', $caps['paper_sizes']);
        $this->assertContains('bw', $caps['color_modes']);
    }

    public function test_submit_and_cancel_print_job(): void
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
            'page_count' => 2,
            'status' => 'ready',
        ]);

        $job = PrintJob::create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'file_id' => $file->id,
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'color_mode' => 'bw',
            'duplex' => false,
            'page_count' => 2,
            'total_price' => 4.00,
            'status' => 'pending',
        ]);

        // Submit
        $result = $this->adapter->submitPrintJob($job);
        $this->assertTrue($result->success);
        $this->assertSame($job->id, $result->jobId);

        $job->refresh();
        $this->assertSame('queued', $job->status);

        // Cancel
        $cancelled = $this->adapter->cancelPrintJob($job->id);
        $this->assertTrue($cancelled);

        $job->refresh();
        $this->assertSame('cancelled', $job->status);
    }

    public function test_printer_status_service_validation(): void
    {
        $valid = $this->statusService->validatePrintOptions(
            paperSize: 'A4',
            paperType: 'bond',
            colorMode: 'bw',
            duplex: false
        );
        $this->assertTrue($valid['valid']);
        $this->assertEmpty($valid['errors']);

        $invalid = $this->statusService->validatePrintOptions(
            paperSize: 'PosterSize_XYZ',
            paperType: 'metal',
            colorMode: 'neon',
            duplex: false
        );
        $this->assertFalse($invalid['valid']);
        $this->assertCount(3, $invalid['errors']);
    }
}
