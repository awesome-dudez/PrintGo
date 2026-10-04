<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PrintJob
 */
class PrintJobResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'file_id' => $this->file_id,
            'file_name' => $this->file?->original_name,
            'status' => $this->status,
            'copies' => (int) $this->copies,
            'paper_size' => $this->paper_size,
            'paper_type' => $this->paper_type,
            'orientation' => $this->orientation,
            'color_mode' => $this->color_mode,
            'duplex' => (bool) $this->duplex,
            'page_count' => (int) $this->page_count,
            'total_pages' => (int) ($this->page_count * $this->copies),
            'pages_completed' => (int) $this->pages_completed,
            'progress' => (int) $this->progress,
            'progress_percentage' => (int) $this->progress,
            'total_price' => (float) $this->total_price,
            'total_amount' => (float) $this->total_price,
            'payment_status' => $this->payment_status,
            'adjustments' => $this->adjustments,
            'idempotency_key' => $this->idempotency_key,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
