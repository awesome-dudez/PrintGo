<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $session_id
 * @property string $original_name
 * @property string $stored_path
 * @property string $file_type
 * @property string $mime_type
 * @property int $file_size
 * @property int $page_count
 * @property string $status
 * @property bool $preview_available
 * @property array<int, string>|null $preview_paths
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class UploadedFile extends Model
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
        'original_name',
        'stored_path',
        'file_type',
        'mime_type',
        'file_size',
        'page_count',
        'status',
        'preview_available',
        'preview_paths',
        'error_message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'page_count' => 'integer',
            'preview_available' => 'boolean',
            'preview_paths' => 'array',
        ];
    }

    /**
     * Get the session that owns the uploaded file.
     *
     * @return BelongsTo<PrintingSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PrintingSession::class, 'session_id');
    }

    /**
     * Get the print jobs created for this file.
     *
     * @return HasMany<PrintJob, $this>
     */
    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class, 'file_id');
    }

    /**
     * Determine if previews are available.
     */
    public function isPreviewAvailable(): bool
    {
        return (bool) $this->preview_available;
    }
}

