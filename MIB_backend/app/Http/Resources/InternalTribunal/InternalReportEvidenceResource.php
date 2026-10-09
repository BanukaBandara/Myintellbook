<?php

namespace App\Http\Resources\InternalTribunal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InternalReportEvidenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'sha256' => $this->sha256,
            'download_url' => url("/api/internal-reports/evidence/{$this->id}/download"),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
