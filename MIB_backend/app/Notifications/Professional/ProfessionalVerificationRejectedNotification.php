<?php

namespace App\Notifications\Professional;

use App\Models\ProfessionalVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfessionalVerificationRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ProfessionalVerification $verification
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "Your professional verification application could not be approved. Reason: {$this->verification->rejection_reason}",
            'verification_id' => $this->verification->id,
            'profession_type' => $this->verification->profession_type->value,
            'status' => 'rejected',
            'rejection_reason' => $this->verification->rejection_reason,
            'url' => '/professional-verification',
            'type' => 'professional_verification_rejected',
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
            ->subject('Professional Verification Application Update')
            ->line('Your professional verification application was not approved.')
            ->line("Reason: {$this->verification->rejection_reason}")
            ->line('You may update your documentation and submit a new application.')
            ->action('Review Application', url('/professional-verification'));
    }
}
