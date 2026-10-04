<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $session_id
 * @property string $file_id
 * @property int|null $printer_id
 * @property int $copies
 * @property string $paper_size
 * @property string $paper_type
 * @property string $orientation
 * @property string $color_mode
 * @property bool $duplex
 * @property array<string, mixed>|null $adjustments
 * @property int $page_count
 * @property string $total_price
 * @property string $payment_status
 * @property string $status
 * @property int $pages_completed
 * @property int $progress
 * @property string|null $idempotency_key
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $queued_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PrintJob extends Model
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
        'session_id',
        'file_id',
        'printer_id',
        'copies',
        'paper_size',
        'paper_type',
        'orientation',
        'color_mode',
        'duplex',
        'adjustments',
        'page_count',
        'total_price',
        'payment_status',
        'status',
        'pages_completed',
        'progress',
        'idempotency_key',
        'error_message',
        'queued_at',
        'started_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'printer_id' => 'integer',
            'copies' => 'integer',
            'duplex' => 'boolean',
            'adjustments' => 'array',
            'page_count' => 'integer',
            'total_price' => 'decimal:2',
            'pages_completed' => 'integer',
            'progress' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
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

    /**
     * Get the uploaded file for this job.
     *
     * @return BelongsTo<UploadedFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(UploadedFile::class, 'file_id');
    }

    /**
     * Alias for file relationship.
     *
     * @return BelongsTo<UploadedFile, $this>
     */
    public function uploadedFile(): BelongsTo
    {
        return $this->file();
    }

    /**
     * Get the printer assigned to this job.
     *
     * @return BelongsTo<Printer, $this>
     */
    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class, 'printer_id');
    }

    /**
     * Get the transaction linked to this print job.
     *
     * @return HasOne<Transaction, $this>
     */
    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'print_job_id');
    }

    /**
     * Check if the job is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the job failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the job is currently printing.
     */
    public function isPrinting(): bool
    {
        return $this->status === 'printing';
    }
}
