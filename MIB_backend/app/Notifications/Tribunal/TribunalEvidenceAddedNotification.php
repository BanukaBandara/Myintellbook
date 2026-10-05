<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalEvidence;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalEvidenceAddedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalEvidence $evidence
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "New evidence {$this->evidence->evidence_number} ('{$this->evidence->title}') was submitted in case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'evidence_id' => $this->evidence->id,
            'evidence_number' => $this->evidence->evidence_number,
            'title' => $this->tribunalCase->title,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_evidence_added',
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
            ->subject("New Evidence Submitted: {$this->tribunalCase->case_number}")
            ->line("New evidence {$this->evidence->evidence_number} was uploaded for case {$this->tribunalCase->case_number}.")
            ->line("Title: {$this->evidence->title}")
            ->action('View Evidence', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
