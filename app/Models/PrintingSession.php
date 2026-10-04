<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string|null $ip_address
 * @property string $status
 * @property \Illuminate\Support\Carbon $last_activity_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PrintingSession extends Model
{
    use HasFactory, HasUuids;

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model's ID is auto-incrementing.
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
        'ip_address',
        'status',
        'last_activity_at',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the latest uploaded file for this session.
     *
     * @return HasOne<UploadedFile, $this>
     */
    public function file(): HasOne
    {
        return $this->hasOne(UploadedFile::class, 'session_id')->latestOfMany();
    }

    /**
     * Get all uploaded files for this session.
     *
     * @return HasMany<UploadedFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(UploadedFile::class, 'session_id');
    }

    /**
     * Get the payment associated with this session.
     *
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'session_id')->latestOfMany();
    }

    /**
     * Get all payments for this session.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'session_id');
    }

    /**
     * Get all print jobs for this session.
     *
     * @return HasMany<PrintJob, $this>
     */
    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class, 'session_id');
    }

    /**
     * Get all transactions for this session.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'session_id');
    }

    /**
     * Check if session has expired.
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired' || $this->expires_at->isPast();
    }

    /**
     * Check if session is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->isExpired();
    }

    /**
     * Refresh last activity timestamp and extend expiration.
     */
    public function touchActivity(int $ttlMinutes = 30): void
    {
        $now = now();
        $this->update([
            'last_activity_at' => $now,
            'expires_at' => $now->copy()->addMinutes($ttlMinutes),
        ]);
    }
}
