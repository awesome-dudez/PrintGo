<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PrintingSession;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * Get the active or latest payment for a given session ID.
     */
    public function getPaymentForSession(string $sessionId): ?Payment
    {
        $session = $this->resolveSession($sessionId);
        $resolvedSessionId = $session ? $session->id : $sessionId;

        return Payment::query()
            ->where('session_id', $resolvedSessionId)
            ->latest('created_at')
            ->first();
    }

    /**
     * Create or update the required payment record for a printing session.
     */
    public function createOrUpdatePayment(string $sessionId, float $requiredAmount): Payment
    {
        $session = $this->resolveSession($sessionId);
        $resolvedSessionId = $session ? $session->id : $sessionId;
        $requiredAmount = max(0.00, round($requiredAmount, 2));

        return DB::transaction(function () use ($resolvedSessionId, $requiredAmount, $session) {
            $payment = Payment::query()
                ->where('session_id', $resolvedSessionId)
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                $payment = Payment::query()->create([
                    'id' => (string) Str::uuid(),
                    'session_id' => $resolvedSessionId,
                    'required_amount' => $requiredAmount,
                    'inserted_amount' => 0.00,
                    'remaining_amount' => $requiredAmount,
                    'status' => $requiredAmount <= 0.0 ? 'paid' : 'pending',
                    'currency' => 'PHP',
                    'payment_method' => 'coin',
                    'paid_at' => $requiredAmount <= 0.0 ? now() : null,
                ]);
            } else {
                $inserted = (float) $payment->inserted_amount;
                $remaining = max(0.00, round($requiredAmount - $inserted, 2));

                $status = 'pending';
                $paidAt = null;
                if ($inserted >= $requiredAmount && $requiredAmount > 0) {
                    $status = 'paid';
                    $paidAt = $payment->paid_at ?? now();
                } elseif ($inserted > 0 && $inserted < $requiredAmount) {
                    $status = 'partial';
                }

                $payment->update([
                    'required_amount' => $requiredAmount,
                    'remaining_amount' => $remaining,
                    'status' => $status,
                    'paid_at' => $paidAt,
                ]);
            }

            if ($session !== null) {
                $session->touchActivity();
            }

            return $payment->fresh();
        });
    }

    /**
     * Record an amount inserted into the payment mechanism (e.g. coin slot, bill validator, or QR).
     */
    public function recordPayment(string $sessionId, float $amountInserted, string $source = 'coin'): Payment
    {
        $session = $this->resolveSession($sessionId);
        $resolvedSessionId = $session ? $session->id : $sessionId;
        $amount = max(0.00, round($amountInserted, 2));

        return DB::transaction(function () use ($resolvedSessionId, $amount, $source, $session) {
            $payment = Payment::query()
                ->where('session_id', $resolvedSessionId)
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                // If payment doesn't exist yet, initialize with 0 required amount
                $payment = Payment::query()->create([
                    'id' => (string) Str::uuid(),
                    'session_id' => $resolvedSessionId,
                    'required_amount' => 0.00,
                    'inserted_amount' => 0.00,
                    'remaining_amount' => 0.00,
                    'status' => 'pending',
                    'currency' => 'PHP',
                    'payment_method' => $source,
                ]);
            }

            $currentInserted = (float) $payment->inserted_amount;
            $newInserted = round($currentInserted + $amount, 2);
            $required = (float) $payment->required_amount;
            $remaining = max(0.00, round($required - $newInserted, 2));

            $status = 'pending';
            $paidAt = $payment->paid_at;

            if ($required > 0 && $newInserted >= $required) {
                $status = 'paid';
                $paidAt = now();
            } elseif ($newInserted > 0 && ($required <= 0 || $newInserted < $required)) {
                $status = ($required > 0) ? 'partial' : 'pending';
            }

            $payment->update([
                'inserted_amount' => $newInserted,
                'remaining_amount' => $remaining,
                'status' => $status,
                'payment_method' => $source,
                'paid_at' => $paidAt,
            ]);

            if ($session !== null) {
                $session->touchActivity();
            }

            Log::info('Payment recorded', [
                'session_id' => $resolvedSessionId,
                'amount_added' => $amount,
                'total_inserted' => $newInserted,
                'status' => $status,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Determine if the session is fully paid.
     */
    public function isPaid(string $sessionId): bool
    {
        $payment = $this->getPaymentForSession($sessionId);

        if ($payment === null) {
            return false;
        }

        return $payment->isPaid();
    }

    /**
     * Resolve PrintingSession by ID or UUID.
     */
    private function resolveSession(string $sessionId): ?PrintingSession
    {
        return PrintingSession::query()->where('id', $sessionId)->first();
    }
}
