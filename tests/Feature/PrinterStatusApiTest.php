<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Printer;
use App\Models\PrinterCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrinterStatusApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_get_printer_status_when_online(): void
    {
        $printer = Printer::query()->create([
            'name' => 'Kiosk Printer 1',
            'model' => 'Epson L3210',
            'connection_type' => 'usb',
            'status' => 'online',
            'is_ready' => true,
            'paper_status' => 'available',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/printer/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'online',
                    'is_ready' => true,
                    'name' => 'Kiosk Printer 1',
                ],
            ]);
    }

    public function test_blocks_uploads_when_printer_is_offline(): void
    {
        Printer::query()->create([
            'name' => 'Kiosk Printer 1',
            'model' => 'Epson L3210',
            'connection_type' => 'usb',
            'status' => 'offline',
            'is_ready' => false,
            'paper_status' => 'available',
            'is_active' => true,
        ]);

        $file = HttpUploadedFile::fake()->create('assignment.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/files', [
            'file' => $file,
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'PRINTER_UNAVAILABLE',
                ],
            ]);
    }

    public function test_blocks_uploads_when_printer_paper_is_empty(): void
    {
        Printer::query()->create([
            'name' => 'Kiosk Printer 1',
            'model' => 'Epson L3210',
            'connection_type' => 'usb',
            'status' => 'online',
            'is_ready' => false,
            'paper_status' => 'empty',
            'is_active' => true,
        ]);

        $file = HttpUploadedFile::fake()->create('assignment.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/files', [
            'file' => $file,
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'PRINTER_UNAVAILABLE',
                ],
            ]);
    }
}
