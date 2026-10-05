<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalSettlementProposal;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalSettlementProposalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalSettlementProposal $proposal,
        public readonly User $proposer,
        public readonly bool $isCounter = false
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $proposerName = $this->proposer->profile?->first_name
            ? "{$this->proposer->profile->first_name} {$this->proposer->profile->last_name}"
            : $this->proposer->email;

        $action = $this->isCounter ? 'submitted a counter-proposal' : 'submitted a settlement proposal';

        return [
            'message' => "{$proposerName} {$action} (v{$this->proposal->version_number}) in Case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'proposal_id' => $this->proposal->id,
            'version_number' => $this->proposal->version_number,
            'proposer_id' => $this->proposer->id,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_settlement_proposal',
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
        $type = $this->isCounter ? 'Counter-Proposal' : 'Settlement Proposal';
        return (new MailMessage)
            ->subject("New {$type}: Case {$this->tribunalCase->case_number}")
            ->line("A new settlement proposal (v{$this->proposal->version_number}) has been submitted for Case {$this->tribunalCase->case_number}.")
            ->action('Review Proposal', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
