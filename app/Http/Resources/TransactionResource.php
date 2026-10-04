<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
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
            'print_job_id' => $this->print_job_id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency ?? 'PHP',
            'payment_status' => $this->payment_status,
            'print_status' => $this->print_status,
            'file_type' => $this->file_type,
            'page_count' => (int) $this->page_count,
            'copies' => (int) $this->copies,
            'total_pages_printed' => (int) ($this->page_count * $this->copies),
            'paper_size' => $this->paper_size,
            'paper_type' => $this->paper_type,
            'orientation' => $this->orientation,
            'color_mode' => $this->color_mode,
            'duplex' => (bool) $this->duplex,
            'print_job' => $this->whenLoaded('printJob', fn () => new PrintJobResource($this->printJob)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
