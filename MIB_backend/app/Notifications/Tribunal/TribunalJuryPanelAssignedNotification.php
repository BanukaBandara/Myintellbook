<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalJuryPanelAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalJuryPanelAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalJuryPanelAssignment $assignment
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "You have been assigned Tribunal Case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'assignment_id' => $this->assignment->id,
            'title' => $this->tribunalCase->title,
            'category' => $this->tribunalCase->category,
            'url' => "/jury/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_jury_panel_assigned',
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
            ->subject("Tribunal Case Assignment: {$this->tribunalCase->case_number}")
            ->line("You have been assigned Tribunal Case {$this->tribunalCase->case_number}.")
            ->action('Open Case in Jury Panel Portal', url("/jury/cases/{$this->tribunalCase->id}"));
    }
}
