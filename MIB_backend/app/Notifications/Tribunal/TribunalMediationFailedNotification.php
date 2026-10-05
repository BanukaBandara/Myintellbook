<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalMediation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalMediationFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalMediation $mediation,
        public readonly ?string $reason = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "Mediation ended without agreement for Case {$this->tribunalCase->case_number}. Case has returned to Tribunal process.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'mediation_id' => $this->mediation->id,
            'reason' => $this->reason,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_mediation_failed',
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
            ->subject("Mediation Concluded: Case {$this->tribunalCase->case_number}")
            ->line("Mediation has concluded without settlement for Case {$this->tribunalCase->case_number}. The case has returned to the Tribunal process.")
            ->action('View Case', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
