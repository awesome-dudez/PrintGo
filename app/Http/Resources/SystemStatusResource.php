<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource['status'] ?? 'online',
            'is_operational' => $this->resource['is_operational'] ?? true,
            'printer' => $this->resource['printer'] ?? null,
            'maintenance_enabled' => (bool) ($this->resource['maintenance_enabled'] ?? false),
            'maintenance_message' => $this->resource['maintenance_message'] ?? null,
            'maintenance' => [
                'enabled' => (bool) ($this->resource['maintenance_enabled'] ?? false),
                'message' => $this->resource['maintenance_message'] ?? null,
            ],
            'active_announcements' => $this->resource['active_announcements'] ?? [],
            'timestamp' => $this->resource['timestamp'] ?? now()->toIso8601String(),
        ];
    }
}
