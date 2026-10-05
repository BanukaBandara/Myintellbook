<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalMediation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalMediationStartedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalMediation $mediation
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "Both parties consented. Mediation is now active for Case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'mediation_id' => $this->mediation->id,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_mediation_started',
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
            ->subject("Mediation Started: Case {$this->tribunalCase->case_number}")
            ->line("Both parties have consented to mediation. The Mediation Workspace is now open for Case {$this->tribunalCase->case_number}.")
            ->action('Open Mediation Workspace', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
