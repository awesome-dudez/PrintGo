<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_with_email(): void
    {
        User::query()->create([
            'name' => 'System Administrator',
            'email' => 'admin@printing.com',
            'username' => 'admin@printing',
            'password' => Hash::make('P@ssw0rd'),
            'is_admin' => true,
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'login' => 'admin@printing.com',
            'password' => 'P@ssw0rd',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'email', 'username', 'role'],
                ],
            ]);

        $this->assertSame('admin@printing.com', $response->json('data.user.email'));
        $this->assertSame('admin@printing', $response->json('data.user.username'));
    }

    public function test_admin_can_login_with_username(): void
    {
        User::query()->create([
            'name' => 'System Administrator',
            'email' => 'admin@printing.com',
            'username' => 'admin@printing',
            'password' => Hash::make('P@ssw0rd'),
            'is_admin' => true,
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'login' => 'admin@printing',
            'password' => 'P@ssw0rd',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'email', 'username', 'role'],
                ],
            ]);

        $this->assertSame('admin@printing', $response->json('data.user.username'));
    }

    public function test_rejects_login_with_invalid_credentials(): void
    {
        User::query()->create([
            'name' => 'System Administrator',
            'email' => 'admin@printing.com',
            'username' => 'admin@printing',
            'password' => Hash::make('P@ssw0rd'),
            'is_admin' => true,
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'login' => 'admin@printing',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                ],
            ]);
    }

    public function test_non_admin_cannot_login(): void
    {
        User::query()->create([
            'name' => 'Regular Student',
            'email' => 'student@school.local',
            'username' => 'student',
            'password' => Hash::make('password123'),
            'is_admin' => false,
            'role' => 'user',
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'login' => 'student@school.local',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                ],
            ]);
    }

    public function test_admin_can_logout_and_revoke_token(): void
    {
        $admin = User::query()->create([
            'name' => 'System Administrator',
            'email' => 'admin@printing.com',
            'username' => 'admin@printing',
            'password' => Hash::make('P@ssw0rd'),
            'is_admin' => true,
            'role' => 'admin',
        ]);

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);

        $this->assertCount(0, $admin->tokens()->get());
    }
}
