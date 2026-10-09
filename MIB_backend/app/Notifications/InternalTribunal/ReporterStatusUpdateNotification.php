<?php

namespace App\Notifications\InternalTribunal;

use App\Models\InternalReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReporterStatusUpdateNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly InternalReport $report,
        public readonly string $status
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'internal_report_status_updated',
            'report_id' => $this->report->id,
            'report_number' => $this->report->report_number,
            'status' => $this->status,
            'message' => "Your misconduct report ({$this->report->report_number}) status has been updated to: {$this->status}.",
            'url' => "/internal-tribunal/{$this->report->id}",
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
