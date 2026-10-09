<?php

namespace App\Notifications\InternalTribunal;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportedUserContentRemovalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $contentCategory = 'Community Resource Note'
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
            'action_type' => 'Content Removal',
            'content_category' => $this->contentCategory,
            'message' => 'A community resource note posted from your account has been removed following an administrative compliance review. If you believe this action was taken in error, please contact platform administration.',
            'issued_at' => now()->toIso8601String(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
