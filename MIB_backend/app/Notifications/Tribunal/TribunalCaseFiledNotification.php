<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalCaseFiledNotification extends Notification
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
            'message' => "A Tribunal case has been filed involving your account (Case #{$this->tribunalCase->case_number}).",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'title' => $this->tribunalCase->title,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_case_filed',
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
            ->subject("Tribunal Case Filed: {$this->tribunalCase->case_number}")
            ->line("A Tribunal case has been filed involving your account.")
            ->line("Case Number: {$this->tribunalCase->case_number}")
            ->line("Title: {$this->tribunalCase->title}")
            ->action('View Case', url("/tribunal/cases/{$this->tribunalCase->id}"))
            ->line('Please review the case details and take required action.');
    }
}
