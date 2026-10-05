<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalRepresentativeAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalRepresentationAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalRepresentativeAssignment $assignment
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $lawyerName = $this->assignment->representative?->profile?->first_name
            ? "{$this->assignment->representative->profile->first_name} {$this->assignment->representative->profile->last_name}"
            : ($this->assignment->representative?->email ?? 'Your attorney');

        return [
            'message' => "Attorney {$lawyerName} has accepted your legal representation request for Case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'assignment_id' => $this->assignment->id,
            'representative_id' => $this->assignment->representative_user_id,
            'representative_name' => $lawyerName,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_representation_accepted',
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
            ->subject("Representation Accepted: {$this->tribunalCase->case_number}")
            ->line("Your legal representation request for Case {$this->tribunalCase->case_number} has been accepted.")
            ->action('Open Case Details', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
