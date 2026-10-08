<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalDecision;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalDecisionPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalDecision $decision
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $case = $this->decision->case;
        $outcomeLabel = $this->decision->outcome ? str_replace('_', ' ', $this->decision->outcome->value) : 'concluded';

        return [
            'message' => "The Tribunal Jury Panel has issued the final decision ({$this->decision->decision_number}) for Case {$case->case_number}. Outcome: {$outcomeLabel}.",
            'case_id' => $case->id,
            'case_number' => $case->case_number,
            'decision_id' => $this->decision->id,
            'decision_number' => $this->decision->decision_number,
            'outcome' => $this->decision->outcome?->value,
            'published_at' => $this->decision->published_at?->toIso8601String(),
            'appeal_deadline' => $this->decision->appeal_deadline?->toIso8601String(),
            'url' => "/tribunal/cases/{$case->id}",
            'type' => 'tribunal_decision_published',
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
        $case = $this->decision->case;
        $outcomeLabel = $this->decision->outcome ? ucwords(str_replace('_', ' ', $this->decision->outcome->value)) : 'Concluded';

        return (new MailMessage)
            ->subject("Final Tribunal Decision Published: Case {$case->case_number} ({$this->decision->decision_number})")
            ->line("The formal Tribunal Jury Panel decision for Case {$case->case_number} has been published.")
            ->line("Outcome: {$outcomeLabel}")
            ->line("Appeal Deadline: " . ($this->decision->appeal_deadline ? $this->decision->appeal_deadline->toFormattedDateString() : 'N/A'))
            ->action('View Formal Decision', url("/tribunal/cases/{$case->id}"));
    }
}
