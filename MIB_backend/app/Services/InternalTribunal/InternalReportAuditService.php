<?php

namespace App\Services\InternalTribunal;

use App\Models\InternalReport;
use App\Models\InternalReportAudit;
use App\Models\User;

class InternalReportAuditService
{
    public function log(InternalReport $report, string $action, ?User $performer = null, array $details = []): InternalReportAudit
    {
        return InternalReportAudit::create([
            'internal_report_id' => $report->id,
            'action' => $action,
            'performed_by' => $performer?->id,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
