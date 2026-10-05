<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalConversation;
use App\Models\TribunalMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalRepresentativeMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalConversation $conversation,
        public readonly TribunalMessage $tribunalMessage,
        public readonly User $sender
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $senderName = $this->sender->profile?->first_name 
            ? "{$this->sender->profile->first_name} {$this->sender->profile->last_name}"
            : $this->sender->email;

        return [
            'message' => "New confidential message from {$senderName} regarding Case {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->tribunalMessage->id,
            'sender_id' => $this->sender->id,
            'sender_name' => $senderName,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_representative_message',
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
        $senderName = $this->sender->profile?->first_name 
            ? "{$this->sender->profile->first_name} {$this->sender->profile->last_name}"
            : $this->sender->email;

        return (new MailMessage)
            ->subject("New Confidential Case Message: {$this->tribunalCase->case_number}")
            ->line("You have received a new confidential message from {$senderName} regarding Tribunal Case {$this->tribunalCase->case_number}.")
            ->action('Open Conversation', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
