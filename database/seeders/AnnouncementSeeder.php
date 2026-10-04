<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Announcement;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Announcement::query()->updateOrCreate(
            ['title' => 'Welcome to Self-Service Printing Kiosk'],
            [
                'content' => 'Upload your documents (PDF, DOCX, PPTX, or Images), configure your print settings, insert payment, and collect your prints.',
                'type' => 'info',
                'is_active' => true,
                'display_order' => 1,
                'starts_at' => null,
                'ends_at' => null,
            ]
        );
    }
}
