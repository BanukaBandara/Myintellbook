<?php

namespace App\Services\Professional;

use App\Models\ProfessionalVerification;
use App\Models\ProfessionalVerificationEvent;

class ProfessionalVerificationEventService
{
    public static function log(
        ProfessionalVerification $verification,
        string $eventType,
        ?int $actorId = null,
        ?array $metadata = null
    ): ProfessionalVerificationEvent {
        return ProfessionalVerificationEvent::create([
            'professional_verification_id' => $verification->id,
            'actor_id' => $actorId ?? auth()->id(),
            'event_type' => $eventType,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
