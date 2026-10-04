<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Printer;
use App\Services\Printing\PrinterStatusDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrinterStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof PrinterStatusDto) {
            $data = $this->resource->toArray();
        } elseif ($this->resource instanceof Printer) {
            $data = [
                'name' => $this->resource->name,
                'status' => $this->resource->status,
                'is_ready' => (bool) $this->resource->is_ready,
                'paper' => ['status' => $this->resource->paper_status],
                'ink' => $this->resource->ink_status,
                'current_job_id' => $this->resource->current_job_id,
                'error_message' => $this->resource->error_message,
            ];
        } else {
            $data = (array) $this->resource;
        }

        return [
            'name' => $data['name'] ?? 'Kiosk Printer 1',
            'status' => $data['status'] ?? 'online',
            'is_ready' => (bool) ($data['is_ready'] ?? $data['ready'] ?? true),
            'paper' => $data['paper'] ?? ['status' => 'available'],
            'ink' => $data['ink'] ?? null,
            'current_job_id' => $data['current_job_id'] ?? $data['currentJob'] ?? null,
            'error_message' => $data['error_message'] ?? $data['error'] ?? null,
            'last_checked_at' => $data['last_checked_at'] ?? now()->toIso8601String(),
        ];
    }
}

