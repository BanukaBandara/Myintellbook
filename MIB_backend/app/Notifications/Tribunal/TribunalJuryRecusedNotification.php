<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalJuryRecusedNotification extends Notification
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
            'message' => "Jury assignment update for case {$this->tribunalCase->case_number}: replacement selection is in progress.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_jury_recused',
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
            ->subject("Tribunal Jury Selection Update: {$this->tribunalCase->case_number}")
            ->line("Jury selection for case {$this->tribunalCase->case_number} is currently in progress.")
            ->action('View Case', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
