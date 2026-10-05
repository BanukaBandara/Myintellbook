<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalMediation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalMediationRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalMediation $mediation,
        public readonly User $initiator
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $initiatorName = $this->initiator->profile?->first_name
            ? "{$this->initiator->profile->first_name} {$this->initiator->profile->last_name}"
            : $this->initiator->email;

        return [
            'message' => "Mediation requested by {$initiatorName} for Case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'mediation_id' => $this->mediation->id,
            'initiator_id' => $this->initiator->id,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_mediation_requested',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Mediation Requested: Case {$this->tribunalCase->case_number}")
            ->line("A party has requested mediation for Case {$this->tribunalCase->case_number}. Your consent is required to proceed.")
            ->action('Review Mediation Request', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
