<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalCaseReportResource extends JsonResource
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
            'tribunal_case_id' => $this->tribunal_case_id,
            'case_number' => $this->case?->case_number,
            'tribunal_decision_id' => $this->tribunal_decision_id,
            'decision_number' => $this->decision?->decision_number,
            'report_number' => $this->report_number,
            'verification_code' => $this->verification_code,
            'report_type' => $this->report_type,
            'status' => $this->status,
            'version' => $this->version,
            'file_hash' => $this->file_hash,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'generated_at' => $this->generated_at?->toIso8601String(),
            'last_downloaded_at' => $this->last_downloaded_at?->toIso8601String(),
            'download_count' => $this->download_count,
            'download_url' => url("/api/tribunal/reports/{$this->id}/download"),
            'verify_url' => rtrim(config('tribunal.report_verify_url', 'http://localhost:5173/tribunal/reports/verify'), '/') . '/' . $this->verification_code,
        ];
    }
}
