<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalHearing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalHearingCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalHearing $hearing
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $case = $this->hearing->case;

        return [
            'message' => "Formal hearing {$this->hearing->hearing_number} has concluded. Case {$case->case_number} has progressed to deliberation.",
            'case_id' => $case->id,
            'case_number' => $case->case_number,
            'hearing_id' => $this->hearing->id,
            'hearing_number' => $this->hearing->hearing_number,
            'url' => "/tribunal/cases/{$case->id}",
            'type' => 'tribunal_hearing_completed',
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
        $case = $this->hearing->case;

        return (new MailMessage)
            ->subject("Hearing Concluded: Case {$case->case_number} ({$this->hearing->hearing_number})")
            ->line("The formal hearing for Case {$case->case_number} has concluded and the case is now ready for deliberation.")
            ->action('View Case Record', url("/tribunal/cases/{$case->id}"));
    }
}
