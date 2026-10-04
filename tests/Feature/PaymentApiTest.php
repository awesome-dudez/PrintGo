<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PrintingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected PrintingSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = PrintingSession::query()->create([
            'status' => 'active',
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    public function test_get_payment_status_for_session(): void
    {
        Payment::query()->create([
            'session_id' => $this->session->id,
            'required_amount' => 10.00,
            'inserted_amount' => 0.00,
            'remaining_amount' => 10.00,
            'status' => 'pending',
            'currency' => 'PHP',
            'payment_method' => 'coin',
        ]);

        $response = $this->getJson("/api/v1/payment/{$this->session->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'session_id' => $this->session->id,
                    'status' => 'pending',
                    'required_amount' => 10.00,
                    'inserted_amount' => 0.00,
                    'remaining_amount' => 10.00,
                    'is_paid' => false,
                    'currency' => 'PHP',
                ],
            ]);
    }

    public function test_coin_insertion_webhook_transitions_to_partial_then_paid(): void
    {
        Payment::query()->create([
            'session_id' => $this->session->id,
            'required_amount' => 10.00,
            'inserted_amount' => 0.00,
            'remaining_amount' => 10.00,
            'status' => 'pending',
            'currency' => 'PHP',
            'payment_method' => 'coin',
        ]);

        // Insert 5.00 PHP -> Partial
        $step1 = $this->postJson('/api/v1/agent/payment/confirm', [
            'session_id' => $this->session->id,
            'amount' => 5.00,
            'source' => 'coin',
        ]);

        $step1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'session_id' => $this->session->id,
                    'status' => 'partial',
                    'required_amount' => 10.00,
                    'inserted_amount' => 5.00,
                    'remaining_amount' => 5.00,
                    'is_paid' => false,
                ],
            ]);

        // Insert another 5.00 PHP -> Fully paid
        $step2 = $this->postJson('/api/v1/agent/payment/confirm', [
            'session_id' => $this->session->id,
            'amount' => 5.00,
            'source' => 'coin',
        ]);

        $step2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'session_id' => $this->session->id,
                    'status' => 'paid',
                    'required_amount' => 10.00,
                    'inserted_amount' => 10.00,
                    'remaining_amount' => 0.00,
                    'is_paid' => true,
                ],
            ]);
    }

    public function test_rejects_confirm_with_nonexistent_session(): void
    {
        $response = $this->postJson('/api/v1/agent/payment/confirm', [
            'session_id' => 'non-existent-uuid',
            'amount' => 5.00,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'SESSION_NOT_FOUND',
                ],
            ]);
    }
}
