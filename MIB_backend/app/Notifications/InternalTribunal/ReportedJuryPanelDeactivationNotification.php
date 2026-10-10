<?php

namespace App\Notifications\InternalTribunal;

use App\Enums\InternalPenaltyType;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportedJuryPanelDeactivationNotification extends Notification
{
    use Queueable;

    public function __construct(
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
            ? "Your Jury Panel account has been temporarily deactivated until {$this->endsAt->toIso8601String()} following an administrative review. During this period, the panel cannot receive new case assignments or perform Jury Panel operations on assigned cases. For assistance, contact platform administration."
            : "Your Jury Panel account has been deactivated following an administrative review. The panel cannot receive new case assignments or perform Jury Panel operations on assigned cases. For assistance, contact platform administration.";

        $payload = [
            'type' => 'internal_jury_panel_deactivation_notice',
            'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
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
