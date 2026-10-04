<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'currency',
                'value' => 'PHP',
                'type' => 'string',
                'description' => 'System-wide currency code.',
            ],
            [
                'key' => 'max_file_size',
                'value' => '52428800',
                'type' => 'integer',
                'description' => 'Maximum allowed file size in bytes (default 50MB).',
            ],
            [
                'key' => 'session_ttl',
                'value' => '30',
                'type' => 'integer',
                'description' => 'Session lifetime in minutes.',
            ],
            [
                'key' => 'file_retention_hours',
                'value' => '24',
                'type' => 'integer',
                'description' => 'Retention period for uploaded and converted files in hours.',
            ],
            [
                'key' => 'kiosk_name',
                'value' => 'Self-Service Print Station #1',
                'type' => 'string',
                'description' => 'Display name of this kiosk terminal.',
            ],
            [
                'key' => 'agent_token',
                'value' => 'kiosk-agent-secret-token-12345',
                'type' => 'string',
                'description' => 'Print agent authentication token.',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
