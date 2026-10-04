<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Printer;
use App\Models\PrinterCapability;
use App\Models\PrintJob;
use App\Models\PrintingSession;
use App\Models\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrintJobApiTest extends TestCase
{
    use RefreshDatabase;

    protected Printer $printer;
    protected PrintingSession $session;
    protected UploadedFile $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = PrintingSession::query()->create([
            'status' => 'active',
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->file = UploadedFile::query()->create([
            'id' => (string) Str::uuid(),
            'session_id' => $this->session->id,
            'original_name' => 'thesis.pdf',
            'stored_path' => 'uploads/thesis.pdf',
            'mime_type' => 'application/pdf',
            'file_type' => 'pdf',
            'file_size' => 4096,
            'page_count' => 5,
            'status' => 'ready',
            'preview_available' => true,
        ]);

        $this->printer = Printer::query()->create([
            'name' => 'Kiosk Printer',
            'model' => 'Epson L3210',
            'connection_type' => 'usb',
            'status' => 'online',
            'is_ready' => true,
            'paper_status' => 'available',
            'is_active' => true,
        ]);

        foreach (['A4', 'Letter', 'bond', 'glossy', 'color', 'bw', 'duplex'] as $cap) {
            PrinterCapability::query()->create([
                'printer_id' => $this->printer->id,
                'capability' => $cap,
                'is_supported' => true,
            ]);
        }
    }

    public function test_submit_print_job_creates_job_and_payment_record(): void
    {
        $response = $this->postJson('/api/v1/print/jobs', [
            'session_id' => $this->session->id,
            'file_id' => $this->file->id,
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
            'idempotency_key' => 'idempotent-key-001',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'session_id' => $this->session->id,
                    'file_id' => $this->file->id,
                    'status' => 'queued',
                    'copies' => 1,
                    'paper_size' => 'A4',
                ],
            ]);

        $jobId = $response->json('data.id');
        $this->assertDatabaseHas('print_jobs', [
            'id' => $jobId,
            'idempotency_key' => 'idempotent-key-001',
        ]);

        $this->assertDatabaseHas('payments', [
            'session_id' => $this->session->id,
        ]);
    }

    public function test_submitting_with_same_idempotency_key_returns_existing_job(): void
    {
        $payload = [
            'session_id' => $this->session->id,
            'file_id' => $this->file->id,
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
            'idempotency_key' => 'duplicate-test-key-999',
        ];

        $res1 = $this->postJson('/api/v1/print/jobs', $payload);
        $res1->assertStatus(201);
        $jobId1 = $res1->json('data.id');

        $res2 = $this->postJson('/api/v1/print/jobs', $payload);
        $res2->assertStatus(200);
        $jobId2 = $res2->json('data.id');

        $this->assertEquals($jobId1, $jobId2);
        $this->assertEquals(1, PrintJob::query()->where('idempotency_key', 'duplicate-test-key-999')->count());
    }

    public function test_get_print_job_status(): void
    {
        $job = PrintJob::query()->create([
            'session_id' => $this->session->id,
            'file_id' => $this->file->id,
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
            'page_count' => 5,
            'pages_completed' => 2,
            'progress' => 40,
            'total_price' => 10.00,
            'status' => 'printing',
            'payment_status' => 'paid',
        ]);

        $response = $this->getJson("/api/v1/print/jobs/{$job->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $job->id,
                    'status' => 'printing',
                    'pages_completed' => 2,
                    'progress' => 40,
                ],
            ]);
    }

    public function test_hardware_agent_can_poll_and_update_job_to_completed(): void
    {
        $job = PrintJob::query()->create([
            'session_id' => $this->session->id,
            'file_id' => $this->file->id,
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
            'page_count' => 5,
            'pages_completed' => 0,
            'progress' => 0,
            'total_price' => 10.00,
            'status' => 'queued',
            'payment_status' => 'paid',
        ]);

        // Agent polls pending jobs
        $pending = $this->getJson('/api/v1/agent/jobs/pending');
        $pending->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Agent marks job as completed
        $update = $this->putJson("/api/v1/agent/jobs/{$job->id}/status", [
            'status' => 'completed',
        ]);

        $update->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $job->id,
                    'status' => 'completed',
                    'progress' => 100,
                ],
            ]);

        // Verify transaction record was created
        $this->assertDatabaseHas('transactions', [
            'print_job_id' => $job->id,
            'print_status' => 'completed',
            'amount' => 10.00,
        ]);
    }
}
