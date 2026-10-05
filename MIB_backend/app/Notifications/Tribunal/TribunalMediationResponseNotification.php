<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalMediation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalMediationResponseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalMediation $mediation,
        public readonly User $responder,
        public readonly string $responseStatus
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $responderName = $this->responder->profile?->first_name
            ? "{$this->responder->profile->first_name} {$this->responder->profile->last_name}"
            : $this->responder->email;

        return [
            'message' => "{$responderName} has {$this->responseStatus} mediation for Case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'mediation_id' => $this->mediation->id,
            'responder_id' => $this->responder->id,
            'response' => $this->responseStatus,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_mediation_response',
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
            ->subject("Mediation Response: Case {$this->tribunalCase->case_number}")
            ->line("A party has {$this->responseStatus} mediation for Case {$this->tribunalCase->case_number}.")
            ->action('View Status', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
