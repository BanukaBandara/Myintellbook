<?php

namespace App\Notifications\Professional;

use App\Models\ProfessionalVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfessionalVerificationSuspendedNotification extends Notification
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
            'message' => "Your professional verification status has been suspended. Reason: {$this->verification->suspension_reason}",
            'verification_id' => $this->verification->id,
            'profession_type' => $this->verification->profession_type->value,
            'status' => 'suspended',
            'suspension_reason' => $this->verification->suspension_reason,
            'url' => '/professional-verification',
            'type' => 'professional_verification_suspended',
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
            ->subject('Professional Verification Suspended')
            ->line('Your professional verification has been suspended.')
            ->line("Reason: {$this->verification->suspension_reason}")
            ->action('View Status', url('/professional-verification'));
    }
}
