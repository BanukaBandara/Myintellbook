<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalWitness;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalWitnessProposedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalWitness $witness
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
            'message' => "Witness '{$this->witness->witness_name}' proposed for Case {$case->case_number} ({$this->witness->side}).",
            'case_id' => $case->id,
            'case_number' => $case->case_number,
            'witness_id' => $this->witness->id,
            'witness_name' => $this->witness->witness_name,
            'side' => $this->witness->side,
            'url' => "/jury/cases/{$case->id}",
            'type' => 'tribunal_witness_proposed',
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
            ->subject("Witness Proposed: Case {$case->case_number}")
            ->line("A new witness ({$this->witness->witness_name}) has been proposed for Case {$case->case_number}.")
            ->action('Review Witness', url("/jury/cases/{$case->id}"));
    }
}
