<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalMediation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalMediationOfferedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalMediation $mediation,
        public readonly User $adjudicator
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $offeredBy = $this->adjudicator->isJuryPanelAccount() ? 'The Tribunal Jury Panel' : 'The Tribunal Adjudicator';

        return [
            'message' => "{$offeredBy} offered mediation for Case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'mediation_id' => $this->mediation->id,
            'adjudicator_id' => $this->adjudicator->id,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_mediation_offered',
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
        $offeredBy = $this->adjudicator->isJuryPanelAccount() ? 'Tribunal Jury Panel' : 'Tribunal Adjudicator';

        return (new MailMessage)
            ->subject("Mediation Offered: Case {$this->tribunalCase->case_number}")
            ->line("The {$offeredBy} has offered voluntary mediation for Case {$this->tribunalCase->case_number}.")
            ->action('Review Mediation Offer', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
