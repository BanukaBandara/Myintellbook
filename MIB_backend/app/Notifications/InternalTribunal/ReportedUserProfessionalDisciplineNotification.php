<?php

namespace App\Notifications\InternalTribunal;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportedUserProfessionalDisciplineNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $actionType,
        public readonly bool $isTemporary = false,
        public readonly ?Carbon $endsAt = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = $this->isTemporary && $this->endsAt
            ? "Your professional eligibility has been temporarily suspended until {$this->endsAt->toIso8601String()} following an administrative review. You will not be eligible to accept new legal representation requests during this period. For questions, contact platform support."
            : "Your professional eligibility has been permanently suspended following an administrative review. You will not be eligible to accept new legal representation requests. For questions, contact platform support.";

        $payload = [
            'type' => 'internal_professional_discipline_notice',
            'action_type' => $this->actionType,
            'message' => $message,
            'is_temporary' => $this->isTemporary,
            'issued_at' => now()->toIso8601String(),
        ];

        if ($this->endsAt) {
            $payload['ends_at'] = $this->endsAt->toIso8601String();
        }

        return $payload;
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
