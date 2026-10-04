<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Printer;
use App\Models\PrinterCapability;
use App\Models\PrintingSession;
use App\Models\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrintSettingsValidationApiTest extends TestCase
{
    use RefreshDatabase;

    protected Printer $printer;
    protected UploadedFile $file;
    protected PrintingSession $session;

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
            'original_name' => 'document.pdf',
            'stored_path' => 'uploads/document.pdf',
            'mime_type' => 'application/pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'page_count' => 4,
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

        foreach (['A4', 'Letter', 'bond', 'glossy', 'color', 'bw'] as $cap) {
            PrinterCapability::query()->create([
                'printer_id' => $this->printer->id,
                'capability' => $cap,
                'is_supported' => true,
            ]);
        }
    }

    public function test_validates_required_print_options(): void
    {
        $response = $this->postJson('/api/v1/print/price', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_rejects_invalid_paper_size(): void
    {
        $response = $this->postJson('/api/v1/print/price', [
            'file_id' => $this->file->id,
            'copies' => 1,
            'paper_size' => 'Tabloid', // invalid
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['paper_size']);
    }

    public function test_rejects_invalid_paper_type(): void
    {
        $response = $this->postJson('/api/v1/print/price', [
            'file_id' => $this->file->id,
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'cardboard', // invalid
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['paper_type']);
    }

    public function test_rejects_negative_or_zero_copies(): void
    {
        $response = $this->postJson('/api/v1/print/price', [
            'file_id' => $this->file->id,
            'copies' => 0,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['copies']);
    }

    public function test_accepts_valid_print_settings(): void
    {
        $response = $this->postJson('/api/v1/print/price', [
            'file_id' => $this->file->id,
            'copies' => 2,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'file_id' => $this->file->id,
                    'copies' => 2,
                    'paper_size' => 'A4',
                    'currency' => 'PHP',
                ],
            ]);
    }

    public function test_normalizes_paper_size_case_insensitively_for_pricing_and_job(): void
    {
        // 1. Lowercase 'letter' and uppercase 'LETTER' in price calculation
        $response = $this->postJson('/api/v1/print/price', [
            'file_id' => $this->file->id,
            'copies' => 1,
            'paper_size' => 'LETTER',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.paper_size', 'Letter');

        // 2. In print job creation
        $jobResponse = $this->postJson('/api/v1/print/jobs', [
            'session_id' => $this->session->id,
            'file_id' => $this->file->id,
            'copies' => 1,
            'paper_size' => 'letter',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
        ]);

        $jobResponse->assertStatus(201)
            ->assertJsonPath('data.paper_size', 'Letter');
    }

    public function test_calculates_price_for_specific_page_range(): void
    {
        // File has 4 pages. Requesting range '1-2' should calculate for 2 pages.
        $response = $this->postJson('/api/v1/print/price', [
            'file_id' => $this->file->id,
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
            'adjustments' => [
                'page_range' => '1-2',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.pages', 2)
            ->assertJsonPath('data.total', 4); // 2 pages * 2.00 base price
    }
}
