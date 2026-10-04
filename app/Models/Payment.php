<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $session_id
 * @property float $required_amount
 * @property float $inserted_amount
 * @property float $remaining_amount
 * @property string $status
 * @property string $currency
 * @property string $payment_method
 * @property string|null $transaction_ref
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Payment extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payments';

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'session_id',
        'required_amount',
        'inserted_amount',
        'remaining_amount',
        'status',
        'currency',
        'payment_method',
        'transaction_ref',
        'paid_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_amount' => 'decimal:2',
            'inserted_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->id)) {
                $payment->id = (string) Str::uuid();
            }
            if ($payment->required_amount === null) {
                $payment->required_amount = 0.00;
            }
            if ($payment->inserted_amount === null) {
                $payment->inserted_amount = 0.00;
            }
            if ($payment->remaining_amount === null) {
                $payment->remaining_amount = (float) $payment->required_amount - (float) $payment->inserted_amount;
            }
            if (empty($payment->currency)) {
                $payment->currency = 'PHP';
            }
            if (empty($payment->status)) {
                $payment->status = 'pending';
            }
            if (empty($payment->payment_method)) {
                $payment->payment_method = 'coin';
            }
        });
    }

    /**
     * Get the session that owns this payment.
     *
     * @return BelongsTo<PrintingSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PrintingSession::class, 'session_id');
    }

    /**
     * Determine whether the payment is fully settled.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid' || ((float) $this->inserted_amount >= (float) $this->required_amount && (float) $this->required_amount > 0);
    }
}
