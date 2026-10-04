<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MaintenanceConfiguration;
use Illuminate\Database\Seeder;

class MaintenanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (MaintenanceConfiguration::query()->count() === 0) {
            MaintenanceConfiguration::query()->create([
                'is_enabled' => false,
                'message' => 'System is undergoing maintenance. Please try again later.',
                'scheduled_until' => null,
            ]);
        }
    }
}
