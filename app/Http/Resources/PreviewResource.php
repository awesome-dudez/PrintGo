<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UploadedFile
 */
class PreviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'file_id' => $this->id,
            'original_name' => $this->original_name,
            'page_count' => (int) $this->page_count,
            'total_pages' => (int) $this->page_count,
            'preview_available' => $this->isPreviewAvailable(),
            'status' => $this->status,
            'pages' => $this->preview_paths,
            'previews' => $this->preview_paths,
        ];
    }
}
