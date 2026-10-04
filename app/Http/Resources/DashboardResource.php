<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'earnings' => [
                'daily' => (float) ($this->resource['earnings']['daily'] ?? 0.00),
                'weekly' => (float) ($this->resource['earnings']['weekly'] ?? 0.00),
                'monthly' => (float) ($this->resource['earnings']['monthly'] ?? 0.00),
                'total' => (float) ($this->resource['earnings']['total'] ?? 0.00),
                'currency' => 'PHP',
            ],
            'total_transactions' => (int) ($this->resource['total_transactions'] ?? 0),
            'total_pages_printed' => (int) ($this->resource['total_pages_printed'] ?? 0),
            'active_jobs' => (int) ($this->resource['active_jobs'] ?? 0),
            'printer' => $this->resource['printer'] ?? null,
            'maintenance' => [
                'enabled' => (bool) ($this->resource['maintenance']['enabled'] ?? false),
                'message' => $this->resource['maintenance']['message'] ?? null,
            ],
            'recent_transactions' => $this->resource['recent_transactions'] ?? [],
        ];
    }
}
