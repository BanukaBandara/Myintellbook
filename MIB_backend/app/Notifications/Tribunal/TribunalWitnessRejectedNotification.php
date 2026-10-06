<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalWitness;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalWitnessRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalWitness $witness,
        public readonly string $reason
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $case = $this->witness->case;

        return [
            'message' => "Witness '{$this->witness->witness_name}' was declined for Case {$case->case_number}. Reason: {$this->reason}",
            'case_id' => $case->id,
            'case_number' => $case->case_number,
            'witness_id' => $this->witness->id,
            'witness_name' => $this->witness->witness_name,
            'reason' => $this->reason,
            'url' => "/tribunal/cases/{$case->id}",
            'type' => 'tribunal_witness_rejected',
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
        $case = $this->witness->case;

        return (new MailMessage)
            ->subject("Witness Request Declined: Case {$case->case_number}")
            ->line("The Jury Panel has declined witness {$this->witness->witness_name} for Case {$case->case_number}.")
            ->line("Reason: {$this->reason}")
            ->action('View Case', url("/tribunal/cases/{$case->id}"));
    }
}
