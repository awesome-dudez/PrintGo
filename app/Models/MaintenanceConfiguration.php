<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property bool $is_enabled
 * @property string|null $message
 * @property int|null $enabled_by
 * @property \Illuminate\Support\Carbon|null $scheduled_until
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class MaintenanceConfiguration extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'is_enabled',
        'message',
        'enabled_by',
        'scheduled_until',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'enabled_by' => 'integer',
            'scheduled_until' => 'datetime',
        ];
    }

    /**
     * Get the current/latest maintenance configuration record.
     */
    public static function current(): ?self
    {
        return static::query()->latest()->first();
    }

    /**
     * Check if maintenance mode is currently active.
     */
    public static function isEnabled(): bool
    {
        $current = static::current();

        if (! $current || ! $current->is_enabled) {
            return false;
        }

        if ($current->scheduled_until !== null && $current->scheduled_until->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get the maintenance message.
     */
    public static function getMessage(): ?string
    {
        return static::current()?->message;
    }

    /**
     * Set or update maintenance configuration.
     */
    public static function setMaintenance(bool $enabled, ?string $message = null, ?int $userId = null): self
    {
        $current = static::current();
        if ($current) {
            $current->update([
                'is_enabled' => $enabled,
                'message' => $message ?? $current->message,
                'enabled_by' => $userId ?? $current->enabled_by,
            ]);
            return $current->fresh();
        }

        return static::query()->create([
            'is_enabled' => $enabled,
            'message' => $message,
            'enabled_by' => $userId,
        ]);
    }

    /**
     * Get the user who enabled maintenance mode.
     *
     * @return BelongsTo<User, $this>
     */
    public function enabledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }
}
