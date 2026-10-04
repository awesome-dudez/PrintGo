<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Payment;
use App\Models\PrintingSession;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = new PaymentService();
    }

    public function test_creates_and_retrieves_payment_for_session(): void
    {
        $session = PrintingSession::create([
            'id' => (string) Str::uuid(),
            'status' => 'active',
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $payment = $this->paymentService->createOrUpdatePayment($session->id, 50.00);

        $this->assertSame(50.00, (float) $payment->required_amount);
        $this->assertSame(0.00, (float) $payment->inserted_amount);
        $this->assertSame(50.00, (float) $payment->remaining_amount);
        $this->assertSame('pending', $payment->status);

        $retrieved = $this->paymentService->getPaymentForSession($session->id);
        $this->assertNotNull($retrieved);
        $this->assertSame($payment->id, $retrieved->id);
        $this->assertFalse($this->paymentService->isPaid($session->id));
    }

    public function test_records_partial_and_full_payments(): void
    {
        $session = PrintingSession::create([
            'id' => (string) Str::uuid(),
            'status' => 'active',
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->paymentService->createOrUpdatePayment($session->id, 20.00);

        // 1. Insert 5 coins
        $partial = $this->paymentService->recordPayment($session->id, 5.00, 'coin');
        $this->assertSame(5.00, (float) $partial->inserted_amount);
        $this->assertSame(15.00, (float) $partial->remaining_amount);
        $this->assertSame('partial', $partial->status);
        $this->assertFalse($this->paymentService->isPaid($session->id));

        // 2. Insert remaining 15 coins
        $paid = $this->paymentService->recordPayment($session->id, 15.00, 'coin');
        $this->assertSame(20.00, (float) $paid->inserted_amount);
        $this->assertSame(0.00, (float) $paid->remaining_amount);
        $this->assertSame('paid', $paid->status);
        $this->assertNotNull($paid->paid_at);
        $this->assertTrue($this->paymentService->isPaid($session->id));
    }
}
