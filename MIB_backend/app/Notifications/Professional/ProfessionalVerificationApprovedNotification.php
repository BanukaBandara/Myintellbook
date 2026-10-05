<?php

namespace App\Notifications\Professional;

use App\Models\ProfessionalVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfessionalVerificationApprovedNotification extends Notification
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
            'message' => "Congratulations! Your professional verification as {$this->verification->profession_type->label()} has been approved.",
            'verification_id' => $this->verification->id,
            'profession_type' => $this->verification->profession_type->value,
            'status' => 'verified',
            'verified_at' => $this->verification->verified_at?->toISOString(),
            'url' => '/professional-verification',
            'type' => 'professional_verification_approved',
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
            ->subject('Professional Verification Approved')
            ->line("Your professional verification as {$this->verification->profession_type->label()} has been successfully approved.")
            ->line('Your profile now displays your Verified Legal Professional status.')
            ->action('View Verification', url('/professional-verification'));
    }
}
