<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalRepresentationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalRepresentationRequestedNotification extends Notification
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
        $clientName = $this->representationRequest->client?->profile?->first_name 
            ? "{$this->representationRequest->client->profile->first_name} {$this->representationRequest->client->profile->last_name}"
            : ($this->representationRequest->client?->email ?? 'A client');

        $side = ucfirst($this->representationRequest->side instanceof \App\Enums\TribunalPartyRole 
            ? $this->representationRequest->side->value 
            : (string) $this->representationRequest->side);

        return [
            'message' => "{$clientName} ({$side}) has requested you as their legal representative for Tribunal Case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'request_id' => $this->representationRequest->id,
            'client_id' => $this->representationRequest->client_user_id,
            'client_name' => $clientName,
            'side' => $side,
            'url' => '/tribunal/representation-requests',
            'type' => 'tribunal_representation_requested',
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
            ->subject("Representation Request: {$this->tribunalCase->case_number}")
            ->line("You have received a new legal representation request for Tribunal Case {$this->tribunalCase->case_number}.")
            ->action('Review Request', url('/tribunal/representation-requests'));
    }
}
