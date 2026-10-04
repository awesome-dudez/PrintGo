<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Printer;
use App\Models\PrinterCapability;
use Illuminate\Database\Seeder;

class PrinterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var Printer $printer */
        $printer = Printer::query()->updateOrCreate(
            ['name' => 'Main Kiosk Printer'],
            [
                'model' => 'Epson EcoTank L3210 / Generic Kiosk',
                'connection_type' => 'agent',
                'ip_address' => '127.0.0.1',
                'status' => 'online',
                'is_ready' => true,
                'error_message' => null,
                'paper_status' => 'available',
                'ink_status' => [
                    'black' => 85,
                    'cyan' => 70,
                    'magenta' => 75,
                    'yellow' => 80,
                ],
                'is_active' => true,
            ]
        );

        $capabilities = [
            'color' => true,
            'duplex' => true,
            'A4' => true,
            'Letter' => true,
            'Legal' => true,
            'bond' => true,
            'glossy' => true,
            'matte' => true,
            'photo' => true,
        ];

        foreach ($capabilities as $capability => $isSupported) {
            PrinterCapability::query()->updateOrCreate(
                [
                    'printer_id' => $printer->id,
                    'capability' => $capability,
                ],
                [
                    'is_supported' => $isSupported,
                ]
            );
        }
    }
}
