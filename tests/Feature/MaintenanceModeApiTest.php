<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MaintenanceConfiguration;
use App\Models\Printer;
use App\Models\PrinterCapability;
use App\Models\PrintingSession;
use App\Models\UploadedFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MaintenanceModeApiTest extends TestCase
{
    use RefreshDatabase;

    protected Printer $printer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->printer = Printer::query()->create([
            'name' => 'Kiosk Printer',
            'model' => 'Epson L3210',
            'connection_type' => 'usb',
            'status' => 'online',
            'is_ready' => true,
            'paper_status' => 'available',
            'is_active' => true,
        ]);

        foreach (['A4', 'bond', 'bw'] as $cap) {
            PrinterCapability::query()->create([
                'printer_id' => $this->printer->id,
                'capability' => $cap,
                'is_supported' => true,
            ]);
        }
    }

    public function test_blocks_file_upload_when_maintenance_is_enabled(): void
    {
        MaintenanceConfiguration::setMaintenance(true, 'System maintenance underway.');

        $file = HttpUploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/files', [
            'file' => $file,
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'MAINTENANCE_MODE',
                    'message' => 'System maintenance underway.',
                ],
            ]);
    }

    public function test_blocks_job_submission_when_maintenance_is_enabled(): void
    {
        MaintenanceConfiguration::setMaintenance(true, 'System maintenance underway.');

        $response = $this->postJson('/api/v1/print/jobs', [
            'file_id' => 'dummy-id',
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'MAINTENANCE_MODE',
                ],
            ]);
    }

    public function test_system_status_reflects_maintenance_mode(): void
    {
        MaintenanceConfiguration::setMaintenance(true, 'Under routine check.');

        $response = $this->getJson('/api/v1/system/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'maintenance',
                    'is_operational' => false,
                    'maintenance_enabled' => true,
                    'maintenance_message' => 'Under routine check.',
                ],
            ]);
    }

    public function test_admin_can_toggle_maintenance_mode(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => 'secret123',
            'is_admin' => true,
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->putJson('/api/v1/admin/maintenance', [
            'enabled' => true,
            'message' => 'Closing kiosk for 30 minutes.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'enabled' => true,
                    'message' => 'Closing kiosk for 30 minutes.',
                ],
            ]);

        $this->assertTrue(MaintenanceConfiguration::isEnabled());

        // Toggle back off
        $responseOff = $this->putJson('/api/v1/admin/maintenance', [
            'enabled' => false,
        ]);

        $responseOff->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'enabled' => false,
                ],
            ]);

        $this->assertFalse(MaintenanceConfiguration::isEnabled());
    }
}
