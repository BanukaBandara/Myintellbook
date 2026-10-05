<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalJuryAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalJuryAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalJuryAssignment $assignment
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "A Tribunal juror has accepted assignment to case {$this->tribunalCase->case_number}. The case is now in evidence collection.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'assignment_id' => $this->assignment->id,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_jury_accepted',
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
            ->subject("Juror Assigned: {$this->tribunalCase->case_number}")
            ->line("A Tribunal juror has accepted assignment to case {$this->tribunalCase->case_number}.")
            ->action('View Case', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
