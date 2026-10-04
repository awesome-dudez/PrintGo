<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceCalculationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'file_id' => $this->resource['file_id'] ?? null,
            'pages' => $this->resource['pages'] ?? 1,
            'copies' => $this->resource['copies'] ?? 1,
            'total_pages' => $this->resource['total_pages'] ?? 1,
            'paper_size' => $this->resource['paper_size'] ?? 'A4',
            'paper_type' => $this->resource['paper_type'] ?? 'bond',
            'color_mode' => $this->resource['color_mode'] ?? 'bw',
            'duplex' => $this->resource['duplex'] ?? false,
            'base_price_per_page' => $this->resource['base_price_per_page'] ?? 0.00,
            'paper_type_fee_per_page' => $this->resource['paper_type_fee_per_page'] ?? 0.00,
            'discount' => $this->resource['discount'] ?? 0.00,
            'total' => $this->resource['total'] ?? $this->resource['total_amount'] ?? 0.00,
            'total_amount' => $this->resource['total_amount'] ?? $this->resource['total'] ?? 0.00,
            'currency' => $this->resource['currency'] ?? 'PHP',
            'breakdown' => $this->resource['breakdown'] ?? [],
        ];
    }
}
