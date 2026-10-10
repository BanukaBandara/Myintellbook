<?php

namespace App\Notifications\InternalTribunal;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportedUserScorePenaltyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly float $pointsDeducted
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $formattedPoints = number_format($this->pointsDeducted, 2);
        // Trim trailing zeros if whole number: e.g. 1,000.00 -> 1,000
        if (str_ends_with($formattedPoints, '.00')) {
            $formattedPoints = substr($formattedPoints, 0, -3);
        }

        return [
            'type' => 'internal_misconduct_notice',
            'action_type' => 'HIP / Score Penalty',
            'points_deducted' => $this->pointsDeducted,
            'message' => "An administrative deduction of {$formattedPoints} points has been applied to your Human Intelligence Portfolio (HIP) score following an administrative review. For assistance or inquiries, please contact platform administration.",
            'issued_at' => now()->toIso8601String(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
