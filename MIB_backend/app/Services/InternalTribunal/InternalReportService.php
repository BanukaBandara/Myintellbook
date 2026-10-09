<?php

namespace App\Services\InternalTribunal;

use App\Enums\InternalReportStatus;
use App\Models\InternalReport;
use App\Models\InternalReportReview;
use App\Models\User;
use App\Notifications\InternalTribunal\ReporterStatusUpdateNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class InternalReportService
{
    public function __construct(
        protected InternalReportEvidenceService $evidenceService,
        protected InternalReportAuditService $auditService
    ) {
    }

    /**
     * Create a new Internal Misconduct Report.
     * Concurrency-safe: creates within transaction, assigns atomic ID, updates IR-YYYY-XXXXXX.
     */
    public function createReport(array $data, ?array $evidenceFiles, User $reporter): InternalReport
    {
        return DB::transaction(function () use ($data, $evidenceFiles, $reporter) {
            $report = InternalReport::create([
                'reporter_user_id' => $reporter->id,
                'reported_user_id' => $data['reported_user_id'],
                'category' => $data['category'],
                'subject' => $data['subject'],
                'description' => $data['description'],
                'status' => InternalReportStatus::Submitted,
                'severity' => $data['severity'] ?? 'medium',
            ]);

            // Safe atomic report number generation
            $reportNumber = sprintf('IR-%s-%06d', now()->year, $report->id);
            $report->update(['report_number' => $reportNumber]);

            // Save evidence if uploaded
            if (!empty($evidenceFiles)) {
                foreach ($evidenceFiles as $file) {
                    if ($file instanceof UploadedFile) {
                        $this->evidenceService->storeEvidenceFile($report, $file, $reporter->id);
                    }
                }
            }

            // Audit record
            $this->auditService->log(
                $report,
                'Report Created',
                $reporter,
                [
                    'category' => $report->category?->value ?? $report->category,
                    'subject' => $report->subject,
                ]
            );

            return $report->fresh(['evidence', 'reportedUser.profile']);
        });
    }

    /**
     * Update report status by Super Admin.
     */
    public function updateStatus(
        InternalReport $report,
        string $newStatus,
        ?string $notes,
        ?string $decisionReason,
        User $admin
    ): InternalReport {
        return DB::transaction(function () use ($report, $newStatus, $notes, $decisionReason, $admin) {
            $fromStatus = $report->status?->value ?? $report->status;

            // Log review transition
            InternalReportReview::create([
                'internal_report_id' => $report->id,
                'reviewed_by' => $admin->id,
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'notes' => $notes,
            ]);

            $updateData = [
                'status' => $newStatus,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ];

            if ($notes !== null) {
                $updateData['admin_notes'] = $notes;
            }

            if ($decisionReason !== null) {
                $updateData['decision_reason'] = $decisionReason;
            }

            if ($newStatus === InternalReportStatus::Closed->value) {
                $updateData['closed_at'] = now();
            }

            $report->update($updateData);

            // Audit
            $this->auditService->log(
                $report,
                "Status Updated: {$fromStatus} -> {$newStatus}",
                $admin,
                [
                    'from' => $fromStatus,
                    'to' => $newStatus,
                    'notes' => $notes,
                ]
            );

            // Notify reporter about the status update
            $reporter = $report->reporter;
            if ($reporter) {
                $reporter->notify(new ReporterStatusUpdateNotification($report, $newStatus));
            }

            return $report->fresh(['reviews', 'evidence', 'penalties', 'audits']);
        });
    }

    /**
     * Get paginated reports submitted by the given reporter.
     */
    public function getUserReports(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return InternalReport::where('reporter_user_id', $userId)
            ->with(['reportedUser.profile', 'evidence'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Get paginated reports for Super Admin with search and filters.
     */
    public function getAdminReports(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = InternalReport::with([
            'reporter.profile',
            'reportedUser.profile',
            'reviewer.profile',
            'evidence',
        ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('report_number', 'like', "%{$term}%")
                    ->orWhere('subject', 'like', "%{$term}%")
                    ->orWhereHas('reporter.profile', function ($sq) use ($term) {
                        $sq->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%");
                    })
                    ->orWhereHas('reportedUser.profile', function ($sq) use ($term) {
                        $sq->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%");
                    });
            });
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }
}
