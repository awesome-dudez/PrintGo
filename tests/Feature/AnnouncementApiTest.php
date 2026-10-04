<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->create([
            'name' => 'Kiosk Admin',
            'email' => 'admin@kiosk.local',
            'password' => 'secret123',
            'is_admin' => true,
            'role' => 'admin',
        ]);
    }

    public function test_public_only_retrieves_active_announcements(): void
    {
        Announcement::query()->create([
            'title' => 'Exam Week Promo',
            'content' => 'Discounted color prints this week.',
            'type' => 'info',
            'is_active' => true,
        ]);

        Announcement::query()->create([
            'title' => 'Old Expired Notice',
            'content' => 'This should not appear in public endpoint.',
            'type' => 'warning',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/announcements');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Exam Week Promo', $response->json('data.0.title'));
    }

    public function test_admin_can_manage_announcement_crud_lifecycle(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        // Create
        $createRes = $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Paper Stock Refilled',
            'content' => 'Glossy and bond paper are both replenished.',
            'type' => 'info',
            'is_active' => true,
        ]);

        $createRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Paper Stock Refilled',
                ],
            ]);

        $announcementId = $createRes->json('data.id');

        // Update
        $updateRes = $this->putJson("/api/v1/admin/announcements/{$announcementId}", [
            'title' => 'Paper Stock Refilled & Ready',
            'is_active' => false,
        ]);

        $updateRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Paper Stock Refilled & Ready',
                    'is_active' => false,
                ],
            ]);

        // Delete
        $deleteRes = $this->deleteJson("/api/v1/admin/announcements/{$announcementId}");
        $deleteRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Announcement deleted successfully.',
            ]);

        $this->assertDatabaseMissing('announcements', [
            'id' => $announcementId,
        ]);
    }
}
