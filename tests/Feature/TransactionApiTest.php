<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@kiosk.local',
            'password' => 'secret123',
            'is_admin' => true,
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_list_and_filter_transactions(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        Transaction::query()->create([
            'id' => (string) Str::uuid(),
            'file_type' => 'pdf',
            'page_count' => 5,
            'copies' => 1,
            'paper_size' => 'A4',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => false,
            'amount' => 10.00,
            'currency' => 'PHP',
            'payment_status' => 'paid',
            'print_status' => 'completed',
        ]);

        Transaction::query()->create([
            'id' => (string) Str::uuid(),
            'file_type' => 'png',
            'page_count' => 1,
            'copies' => 2,
            'paper_size' => 'A4',
            'paper_type' => 'glossy',
            'orientation' => 'portrait',
            'color_mode' => 'color',
            'duplex' => false,
            'amount' => 16.00,
            'currency' => 'PHP',
            'payment_status' => 'paid',
            'print_status' => 'completed',
        ]);

        // Filter by file_type = pdf
        $response = $this->getJson('/api/v1/admin/transactions?file_type=pdf');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pagination' => [
                        'total' => 1,
                    ],
                ],
            ]);

        $this->assertEquals('pdf', $response->json('data.transactions.0.file_type'));
    }

    public function test_admin_can_view_single_transaction_details(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $tx = Transaction::query()->create([
            'id' => (string) Str::uuid(),
            'file_type' => 'pdf',
            'page_count' => 4,
            'copies' => 2,
            'paper_size' => 'Letter',
            'paper_type' => 'bond',
            'orientation' => 'portrait',
            'color_mode' => 'bw',
            'duplex' => true,
            'amount' => 15.00,
            'currency' => 'PHP',
            'payment_status' => 'paid',
            'print_status' => 'completed',
        ]);

        $response = $this->getJson("/api/v1/admin/transactions/{$tx->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $tx->id,
                    'amount' => 15.00,
                    'page_count' => 4,
                    'copies' => 2,
                    'total_pages_printed' => 8,
                    'paper_size' => 'Letter',
                ],
            ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/admin/transactions');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                ],
            ]);
    }
}
