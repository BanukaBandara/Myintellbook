<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalDecisionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $panel = $this->juryPanel;
        $case = $this->case;

        return [
            'id' => $this->id,
            'decision_number' => $this->decision_number,
            'case_id' => $this->tribunal_case_id,
            'case_number' => $case?->case_number,
            'status' => $this->status?->value ?? (string) $this->status,
            'outcome' => $this->outcome?->value ?? (string) $this->outcome,
            'summary' => $this->summary,
            'reasoning' => $this->reasoning,
            'published_at' => $this->published_at?->toIso8601String(),
            'appeal_deadline' => $this->appeal_deadline?->toIso8601String(),
            'jury_panel' => $panel ? [
                'id' => $panel->id,
                'panel_code' => $panel->panel_code,
                'panel_name' => $panel->panel_name,
            ] : null,
            'findings' => $case ? $case->findings()
                ->where('is_public', true)
                ->with(['evidence', 'witnesses'])
                ->get()
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'finding_number' => $f->finding_number,
                    'finding_type' => $f->finding_type?->value ?? (string) $f->finding_type,
                    'title' => $f->title,
                    'finding_text' => $f->finding_text,
                    'conclusion' => $f->conclusion?->value ?? (string) $f->conclusion,
                    'display_order' => $f->display_order,
                    'evidence_references' => $f->evidence->map(fn ($e) => [
                        'id' => $e->id,
                        'evidence_number' => $e->evidence_number,
                        'title' => $e->title,
                    ]),
                    'witness_references' => $f->witnesses->map(fn ($w) => [
                        'id' => $w->id,
                        'witness_name' => $w->witness_name,
                        'side' => $w->side,
                    ]),
                ]) : [],
            'orders' => $this->orders->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'order_type' => $o->order_type?->value ?? (string) $o->order_type,
                'title' => $o->title,
                'description' => $o->description,
                'target_side' => $o->target_side,
                'deadline_at' => $o->deadline_at?->toIso8601String(),
                'status' => $o->status?->value ?? (string) $o->status,
            ]),
        ];
    }
}
