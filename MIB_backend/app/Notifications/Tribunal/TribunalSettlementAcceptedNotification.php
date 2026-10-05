<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalSettlementProposal;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalSettlementAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalSettlementProposal $proposal,
        public readonly User $acceptingParty
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $partyName = $this->acceptingParty->profile?->first_name
            ? "{$this->acceptingParty->profile->first_name} {$this->acceptingParty->profile->last_name}"
            : $this->acceptingParty->email;

        return [
            'message' => "{$partyName} accepted settlement proposal v{$this->proposal->version_number} in Case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'proposal_id' => $this->proposal->id,
            'user_id' => $this->acceptingParty->id,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_settlement_accepted',
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
            ->subject("Proposal Accepted: Case {$this->tribunalCase->case_number}")
            ->line("A party has accepted settlement proposal v{$this->proposal->version_number} in Case {$this->tribunalCase->case_number}.")
            ->action('View Mediation Status', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
