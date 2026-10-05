<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalSettlementAgreement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalSettlementReachedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalSettlementAgreement $agreement
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "Settlement agreement finalized ({$this->agreement->agreement_number}) for Case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'agreement_id' => $this->agreement->id,
            'agreement_number' => $this->agreement->agreement_number,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_settlement_reached',
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
            ->subject("Settlement Reached: Case {$this->tribunalCase->case_number}")
            ->line("A binding settlement agreement ({$this->agreement->agreement_number}) has been finalized for Case {$this->tribunalCase->case_number}.")
            ->action('View Settlement Agreement', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
