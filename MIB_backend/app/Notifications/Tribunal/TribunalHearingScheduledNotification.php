<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalHearing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalHearingScheduledNotification extends Notification
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
        $scheduledTime = $this->hearing->scheduled_at ? $this->hearing->scheduled_at->toIso8601String() : 'TBD';

        return [
            'message' => "Formal hearing {$this->hearing->hearing_number} has been scheduled for Case {$case->case_number}.",
            'case_id' => $case->id,
            'case_number' => $case->case_number,
            'hearing_id' => $this->hearing->id,
            'hearing_number' => $this->hearing->hearing_number,
            'scheduled_at' => $scheduledTime,
            'url' => "/tribunal/cases/{$case->id}",
            'type' => 'tribunal_hearing_scheduled',
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
            ->subject("Hearing Scheduled: Case {$case->case_number} ({$this->hearing->hearing_number})")
            ->line("A formal hearing has been scheduled by the Jury Panel for Case {$case->case_number}.")
            ->action('View Case Hearing', url("/tribunal/cases/{$case->id}"));
    }
}
