<?php

namespace App\Notifications\Professional;

use App\Models\ProfessionalVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfessionalVerificationSubmittedNotification extends Notification
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
            'message' => "Your professional verification application as {$this->verification->profession_type->label()} has been received and is pending review.",
            'verification_id' => $this->verification->id,
            'profession_type' => $this->verification->profession_type->value,
            'status' => $this->verification->verification_status->value,
            'url' => '/professional-verification',
            'type' => 'professional_verification_submitted',
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
            ->subject('Professional Verification Application Received')
            ->line("Your application for verification as {$this->verification->profession_type->label()} has been received.")
            ->line('Our review committee will verify your submitted credentials.')
            ->action('View Status', url('/professional-verification'));
    }
}
