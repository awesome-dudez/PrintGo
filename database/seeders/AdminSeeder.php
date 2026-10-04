<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clean up legacy default if present
        User::query()->where('email', 'admin@printing.local')->delete();

        User::query()->updateOrCreate(
            ['email' => 'admin@printing.com'],
            [
                'name' => 'System Administrator',
                'username' => 'admin@printing',
                'password' => Hash::make('P@ssw0rd'),
                'is_admin' => true,
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}
