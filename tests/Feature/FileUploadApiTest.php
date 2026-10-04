<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Printer;
use App\Models\PrinterCapability;
use App\Models\PrintingSession;
use App\Models\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileUploadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        // Ensure active ready printer exists so CheckPrinterAvailable middleware passes
        $printer = Printer::query()->create([
            'name' => 'Test Kiosk Printer',
            'model' => 'Epson L3210',
            'connection_type' => 'usb',
            'status' => 'online',
            'is_ready' => true,
            'paper_status' => 'available',
            'is_active' => true,
        ]);

        foreach (['A4', 'Letter', 'Legal', 'bond', 'glossy', 'matte', 'photo', 'color', 'duplex'] as $cap) {
            PrinterCapability::query()->create([
                'printer_id' => $printer->id,
                'capability' => $cap,
                'is_supported' => true,
            ]);
        }
    }

    public function test_can_upload_valid_pdf_file(): void
    {
        $file = HttpUploadedFile::fake()->create('assignment.pdf', 150, 'application/pdf');

        $response = $this->postJson('/api/v1/files', [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'session_id',
                    'original_name',
                    'file_type',
                    'mime_type',
                    'file_size',
                    'page_count',
                    'status',
                    'preview_available',
                ],
            ]);

        $fileId = $response->json('data.id');
        $this->assertDatabaseHas('uploaded_files', [
            'id' => $fileId,
            'original_name' => 'assignment.pdf',
            'file_type' => 'pdf',
        ]);
    }

    public function test_can_upload_valid_image_file(): void
    {
        $file = HttpUploadedFile::fake()->image('photo.png', 200, 200);

        $response = $this->postJson('/api/v1/files', [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'file_type' => 'png',
                    'page_count' => 1,
                ],
            ]);
    }

    public function test_rejects_unsupported_file_extension(): void
    {
        $file = HttpUploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

        $response = $this->postJson('/api/v1/files', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_rejects_file_exceeding_max_size(): void
    {
        // 55MB exceeds 50MB (51200 KB) limit
        $file = HttpUploadedFile::fake()->create('huge.pdf', 55000, 'application/pdf');

        $response = $this->postJson('/api/v1/files', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_can_bind_file_upload_to_existing_session(): void
    {
        $session = PrintingSession::query()->create([
            'status' => 'active',
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $file = HttpUploadedFile::fake()->create('notes.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/files', [
            'file' => $file,
            'session_id' => $session->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'session_id' => $session->id,
                ],
            ]);
    }

    public function test_can_retrieve_uploaded_file_status_and_previews(): void
    {
        $session = PrintingSession::query()->create([
            'status' => 'active',
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $uploaded = UploadedFile::query()->create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'original_name' => 'test.pdf',
            'stored_path' => 'uploads/test.pdf',
            'mime_type' => 'application/pdf',
            'file_type' => 'pdf',
            'file_size' => 12345,
            'page_count' => 3,
            'status' => 'ready',
            'preview_available' => true,
            'preview_paths' => ['previews/page_1.png', 'previews/page_2.png'],
        ]);

        $showResponse = $this->getJson("/api/v1/files/{$uploaded->id}");
        $showResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $uploaded->id,
                    'page_count' => 3,
                    'status' => 'ready',
                ],
            ]);

        $previewResponse = $this->getJson("/api/v1/files/{$uploaded->id}/preview");
        $previewResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'file_id' => $uploaded->id,
                    'page_count' => 3,
                ],
            ]);
    }
}
