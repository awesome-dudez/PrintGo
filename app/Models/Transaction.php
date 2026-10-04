<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $print_job_id
 * @property string|null $session_id
 * @property string $file_type
 * @property int $page_count
 * @property int $copies
 * @property string $paper_size
 * @property string $paper_type
 * @property string $orientation
 * @property string $color_mode
 * @property bool $duplex
 * @property string $amount
 * @property string $currency
 * @property string $payment_status
 * @property string $print_status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Transaction extends Model
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
        'print_job_id',
        'session_id',
        'file_type',
        'page_count',
        'copies',
        'paper_size',
        'paper_type',
        'orientation',
        'color_mode',
        'duplex',
        'amount',
        'currency',
        'payment_status',
        'print_status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_count' => 'integer',
            'copies' => 'integer',
            'duplex' => 'boolean',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Get the print job that originated this transaction.
     *
     * @return BelongsTo<PrintJob, $this>
     */
    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class, 'print_job_id');
    }

    /**
     * Get the printing session.
     *
     * @return BelongsTo<PrintingSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PrintingSession::class, 'session_id');
    }
}
