<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UploadedFile
 */
class UploadedFileResource extends JsonResource
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
            'session_token' => $this->session?->session_token,
            'original_name' => $this->original_name,
            'file_type' => $this->file_type,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'page_count' => $this->page_count,
            'status' => $this->status,
            'preview_available' => $this->isPreviewAvailable(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
