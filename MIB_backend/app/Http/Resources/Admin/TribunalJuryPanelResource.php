<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalJuryPanelResource extends JsonResource
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
            'panel_code' => $this->panel_code,
            'panel_name' => $this->panel_name,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status,
            'login_user_id' => $this->login_user_id,
            'login_email' => $this->loginUser?->email,
            'assigned_cases_count' => $this->panelAssignments()->count(),
            'active_cases_count' => $this->activeAssignments()->count(),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'email' => $this->creator->email,
                ];
            }),
            'events' => $this->whenLoaded('events', function () {
                return $this->events->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'event_type' => $event->event_type,
                        'actor_id' => $event->actor_id,
                        'metadata' => $event->metadata,
                        'created_at' => $event->created_at?->toISOString(),
                    ];
                });
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
