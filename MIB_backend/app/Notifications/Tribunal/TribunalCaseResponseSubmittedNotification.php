<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalCaseResponseSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "The respondent has submitted a response to Tribunal case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'title' => $this->tribunalCase->title,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_case_response_submitted',
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
            ->subject("Tribunal Case Response Submitted: {$this->tribunalCase->case_number}")
            ->line("The respondent has submitted a formal response to Tribunal case {$this->tribunalCase->case_number}.")
            ->line("Case Number: {$this->tribunalCase->case_number}")
            ->line("Title: {$this->tribunalCase->title}")
            ->action('View Response', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
