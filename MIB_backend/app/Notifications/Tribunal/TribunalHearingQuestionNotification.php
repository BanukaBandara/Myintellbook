<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalHearing;
use App\Models\TribunalHearingEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalHearingQuestionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalHearing $hearing,
        public readonly TribunalHearingEntry $question
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $case = $this->hearing->case;
        $target = $this->question->target_side ? ucfirst($this->question->target_side) : 'Participants';

        return [
            'message' => "The Jury Panel has asked a hearing question targeted to {$target} in Case {$case->case_number}.",
            'case_id' => $case->id,
            'case_number' => $case->case_number,
            'hearing_id' => $this->hearing->id,
            'entry_id' => $this->question->id,
            'target_side' => $this->question->target_side,
            'url' => "/tribunal/cases/{$case->id}",
            'type' => 'tribunal_hearing_question',
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
        $case = $this->hearing->case;

        return (new MailMessage)
            ->subject("Hearing Question Directed to You: Case {$case->case_number}")
            ->line("The Jury Panel has directed a question during the formal hearing in Case {$case->case_number}.")
            ->action('Respond in Hearing', url("/tribunal/cases/{$case->id}"));
    }
}
