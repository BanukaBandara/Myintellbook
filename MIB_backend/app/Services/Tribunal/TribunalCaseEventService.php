<?php

namespace App\Services\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalCaseEvent;

class TribunalCaseEventService
{
    public static function log(
        TribunalCase $case,
        string $eventType,
        ?int $actorId = null,
        ?array $metadata = null
    ): TribunalCaseEvent {
        return TribunalCaseEvent::create([
            'tribunal_case_id' => $case->id,
            'actor_id' => $actorId ?? auth()->id(),
            'event_type' => $eventType,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
