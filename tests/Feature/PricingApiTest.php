<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PricingConfiguration;
use App\Models\Printer;
use App\Models\PrinterCapability;
use App\Models\PrintingSession;
use App\Models\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PricingApiTest extends TestCase
{
    use RefreshDatabase;

    protected UploadedFile $file;

    protected function setUp(): void
    {
        parent::setUp();

        $session = PrintingSession::query()->create([
            'status' => 'active',
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->file = UploadedFile::query()->create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'original_name' => 'test.pdf',
            'stored_path' => 'uploads/test.pdf',
            'mime_type' => 'application/pdf',
            'file_type' => 'pdf',
            'file_size' => 2048,
            'page_count' => 10,
            'status' => 'ready',
            'preview_available' => true,
        ]);

        $printer = Printer::query()->create([
            'name' => 'Kiosk Printer',
            'model' => 'Epson L3210',
            'connection_type' => 'usb',
            'status' => 'online',
            'is_ready' => true,
            'paper_status' => 'available',
            'is_active' => true,
        ]);

        foreach (['A4', 'Letter', 'Legal', 'bond', 'glossy', 'color', 'bw', 'duplex'] as $cap) {
            PrinterCapability::query()->create([
                'printer_id' => $printer->id,
                'capability' => $cap,
                'is_supported' => true,
            ]);
        }

        PricingConfiguration::updatePricing([
            'bw_a4' => 2.00,
            'color_a4' => 5.00,
            'paper_bond' => 0.00,
            'paper_glossy' => 3.00,
            'size_legal' => 1.00,
            'duplex_discount' => 0.50,
        ]);
    }

    public function test_bw_single_copy_pricing(): void
    {
        // 10 pages * 2.00 = 20.00 PHP
        $response = $this->postJson('/api/v1/print/price', [
            'file_id' => $this->file->id,
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(20.00, $response->json('data.total'));
        $this->assertEquals('PHP', $response->json('data.currency'));
    }

    public function test_color_pricing_with_paper_surcharge(): void
    {
        // Base color A4 = 5.00, glossy = 3.00 -> 8.00 per page * 10 = 80.00 PHP
        $response = $this->postJson('/api/v1/print/price', [
            'file_id' => $this->file->id,
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'glossy',
            'orientation' => 'portrait',
            'color_mode' => 'color',
            'duplex' => false,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(80.00, $response->json('data.total'));
    }

    public function test_duplex_discount_application(): void
    {
        // 10 pages BW = 20.00. Duplex saves 5 sheets * 0.50 = 2.50 discount -> total 17.50
        $response = $this->postJson('/api/v1/print/price', [
            'file_id' => $this->file->id,
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => true,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(17.50, $response->json('data.total'));
    }

    public function test_multiple_copies_multiplication(): void
    {
        // 10 pages BW = 20.00 * 3 copies = 60.00 PHP
        $response = $this->postJson('/api/v1/print/price', [
            'file_id' => $this->file->id,
            'copies' => 3,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(60.00, $response->json('data.total'));
        $this->assertEquals(3, $response->json('data.copies'));
    }
}
