<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use App\Models\PrintingSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof Payment) {
            $payment = $this->resource;
            $sessionId = $payment->session_id;
            $status = $payment->status;
            $required = (float) $payment->required_amount;
            $inserted = (float) $payment->inserted_amount;
            $remaining = (float) $payment->remaining_amount;
            $isPaid = $payment->isPaid();
            $currency = $payment->currency ?? 'PHP';
        } else {
            /** @var PrintingSession $session */
            $session = $this->resource;
            $sessionId = $session->id;
            $payment = $session->payment;
            $status = $payment?->status ?? 'pending';
            $required = (float) ($payment?->required_amount ?? 0.0);
            $inserted = (float) ($payment?->inserted_amount ?? 0.0);
            $remaining = (float) ($payment?->remaining_amount ?? $required);
            $isPaid = $payment ? $payment->isPaid() : false;
            $currency = $payment?->currency ?? 'PHP';
        }

        return [
            'session_id' => $sessionId,
            'status' => $status,
            'required_amount' => $required,
            'inserted_amount' => $inserted,
            'remaining_amount' => $remaining,
            'is_paid' => $isPaid,
            'currency' => $currency,
        ];
    }
}

