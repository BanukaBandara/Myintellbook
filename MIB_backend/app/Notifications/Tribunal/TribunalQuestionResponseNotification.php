<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalCaseMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalQuestionResponseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalCaseMessage $responseMessage,
        public readonly User $sender
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $senderName = $this->sender->profile?->first_name
            ? "{$this->sender->profile->first_name} {$this->sender->profile->last_name}"
            : $this->sender->email;

        return [
            'message' => "{$senderName} responded to an Adjudicator question in Case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'case_message_id' => $this->responseMessage->id,
            'question_id' => $this->responseMessage->parent_message_id,
            'sender_id' => $this->sender->id,
            'sender_name' => $senderName,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_question_response',
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
        $senderName = $this->sender->profile?->first_name
            ? "{$this->sender->profile->first_name} {$this->sender->profile->last_name}"
            : $this->sender->email;

        return (new MailMessage)
            ->subject("Response to Adjudicator Question: Case {$this->tribunalCase->case_number}")
            ->line("{$senderName} has submitted a response to an adjudicator question in Case {$this->tribunalCase->case_number}.")
            ->action('View Response', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
