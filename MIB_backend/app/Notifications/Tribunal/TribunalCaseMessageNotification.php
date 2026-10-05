<?php

namespace App\Notifications\Tribunal;

use App\Models\TribunalCase;
use App\Models\TribunalCaseMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TribunalCaseMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TribunalCase $tribunalCase,
        public readonly TribunalCaseMessage $message,
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
            'message' => "New message from {$senderName} in Case Room {$this->tribunalCase->case_number}.",
            'case_id' => $this->tribunalCase->id,
            'case_number' => $this->tribunalCase->case_number,
            'case_message_id' => $this->message->id,
            'sender_id' => $this->sender->id,
            'sender_name' => $senderName,
            'url' => "/tribunal/cases/{$this->tribunalCase->id}",
            'type' => 'tribunal_case_message',
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
            ->subject("New Case Room Message: {$this->tribunalCase->case_number}")
            ->line("New message from {$senderName} in Tribunal Case {$this->tribunalCase->case_number}.")
            ->action('Open Case Room', url("/tribunal/cases/{$this->tribunalCase->id}"));
    }
}
