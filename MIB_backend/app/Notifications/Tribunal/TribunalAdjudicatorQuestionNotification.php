<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalCaseMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalAdjudicatorQuestionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalCaseMessage $question,
        public readonly User $adjudicator
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $target = $this->question->target_side ? ucfirst($this->question->target_side) : 'Parties';

        return [
            'message' => "Adjudicator question posed to {$target} in Case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'case_message_id' => $this->question->id,
            'target_side' => $this->question->target_side,
            'adjudicator_id' => $this->adjudicator->id,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_adjudicator_question',
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
            ->subject("Adjudicator Question: Case {$this->tribunalCase->case_number}")
            ->line("The Tribunal Adjudicator has posed a question in Case {$this->tribunalCase->case_number}.")
            ->action('Respond to Question', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
