<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalJuryAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalJuryAssignedNotification extends Notification
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
            'message' => "You have been selected as a juror for Tribunal case {$this->tribunalCase->case_number}. Please submit your conflict declaration.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'assignment_id' => $this->assignment->id,
            'title' => $this->tribunalCase->title,
            'category' => $this->tribunalCase->category,
            'url' => '/tribunal/jury',
            'type' => 'tribunal_jury_assigned',
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
            ->subject("Tribunal Juror Assignment: {$this->tribunalCase->case_number}")
            ->line("You have been randomly selected as a juror for Tribunal case {$this->tribunalCase->case_number}.")
            ->line("Please review the case metadata and complete your conflict-of-interest declaration.")
            ->action('Open Juror Portal', url('/tribunal/jury'));
    }
}
