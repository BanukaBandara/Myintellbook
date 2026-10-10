<?php

namespace App\Notifications\InternalTribunal;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportedUserSanitizedActionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $actionType,
        public readonly string $reason
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'internal_misconduct_notice',
            'action_type' => $this->actionType,
            'message' => "An administrative action ({$this->actionType}) has been issued regarding platform misconduct: {$this->reason}",
            'reason' => $this->reason,
            'issued_at' => now()->toIso8601String(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
