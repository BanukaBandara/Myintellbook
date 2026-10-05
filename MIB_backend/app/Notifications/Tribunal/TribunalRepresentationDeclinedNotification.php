<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalRepresentationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalRepresentationDeclinedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalRepresentationRequest $representationRequest
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $lawyerName = $this->representationRequest->representative?->profile?->first_name
            ? "{$this->representationRequest->representative->profile->first_name} {$this->representationRequest->representative->profile->last_name}"
            : ($this->representationRequest->representative?->email ?? 'The attorney');

        return [
            'message' => "Your representation request for Case {$this->tribunalCase->case_number} was declined by {$lawyerName}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'request_id' => $this->representationRequest->id,
            'decline_reason' => $this->representationRequest->decline_reason,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_representation_declined',
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
            ->subject("Representation Request Declined: {$this->tribunalCase->case_number}")
            ->line("Your representation request for Case {$this->tribunalCase->case_number} was declined.")
            ->line("Reason: " . ($this->representationRequest->decline_reason ?: 'No specific reason provided.'))
            ->action('Find Another Representative', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
