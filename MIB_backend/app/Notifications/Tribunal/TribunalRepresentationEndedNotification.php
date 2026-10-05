<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalRepresentativeAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalRepresentationEndedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalRepresentativeAssignment $assignment,
        public readonly ?string $reason = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "Legal representation for Case {$this->tribunalCase->case_number} has been ended.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'assignment_id' => $this->assignment->id,
            'reason' => $this->reason,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_representation_ended',
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
            ->subject("Representation Ended: {$this->tribunalCase->case_number}")
            ->line("Legal representation for Case {$this->tribunalCase->case_number} has concluded.")
            ->line("Reason: " . ($this->reason ?: 'Representation concluded.'))
            ->action('View Case', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
