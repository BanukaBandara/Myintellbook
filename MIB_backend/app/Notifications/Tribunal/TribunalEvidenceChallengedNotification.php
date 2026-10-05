<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalEvidence;
use App\Models\TribunalEvidenceChallenge;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalEvidenceChallengedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalEvidence $evidence,
        public readonly TribunalEvidenceChallenge $challenge
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "Evidence {$this->evidence->evidence_number} has been challenged in case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'evidence_id' => $this->evidence->id,
            'evidence_number' => $this->evidence->evidence_number,
            'challenge_id' => $this->challenge->id,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_evidence_challenged',
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
            ->subject("Evidence Challenged: {$this->tribunalCase->case_number}")
            ->line("Evidence {$this->evidence->evidence_number} has been challenged in case {$this->tribunalCase->case_number}.")
            ->line("Reason: {$this->challenge->reason}")
            ->action('View Details', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
