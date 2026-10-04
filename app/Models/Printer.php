<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $model
 * @property string $connection_type
 * @property string|null $ip_address
 * @property string $status
 * @property bool $is_ready
 * @property string|null $error_message
 * @property string $paper_status
 * @property array<string, mixed>|null $ink_status
 * @property string|null $current_job_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Printer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'model',
        'connection_type',
        'ip_address',
        'status',
        'is_ready',
        'error_message',
        'paper_status',
        'ink_status',
        'current_job_id',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_ready' => 'boolean',
            'is_active' => 'boolean',
            'ink_status' => 'array',
        ];
    }

    /**
     * Scope a query to only include active printers.
     *
     * @param  Builder<Printer>  $query
     * @return Builder<Printer>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the capabilities associated with this printer.
     *
     * @return HasMany<PrinterCapability, $this>
     */
    public function capabilities(): HasMany
    {
        return $this->hasMany(PrinterCapability::class);
    }

    /**
     * Get the print jobs assigned to this printer.
     *
     * @return HasMany<PrintJob, $this>
     */
    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    /**
     * Check if printer supports a given capability.
     */
    public function hasCapability(string $capability): bool
    {
        return $this->capabilities()
            ->where('capability', $capability)
            ->where('is_supported', true)
            ->exists();
    }
}
