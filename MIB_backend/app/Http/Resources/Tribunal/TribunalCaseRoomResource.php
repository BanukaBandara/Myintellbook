<?php

namespace App\Http\Resources\Tribunal;

use App\Models\TribunalCaseRoom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalCaseRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TribunalCaseRoom $room */
        $room = $this->resource;

        return [
            'id' => $room->id,
            'tribunal_case_id' => $room->tribunal_case_id,
            'status' => $room->status?->value ?? (string) $room->status,
            'opened_at' => $room->opened_at?->toIso8601String(),
            'closed_at' => $room->closed_at?->toIso8601String(),
            'created_at' => $room->created_at?->toIso8601String(),
            'messages_count' => $this->whenCounted('messages'),
        ];
    }
}
