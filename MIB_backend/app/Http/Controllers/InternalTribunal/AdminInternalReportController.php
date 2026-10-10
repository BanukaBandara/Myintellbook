<?php

namespace App\Http\Controllers\InternalTribunal;

use App\Http\Controllers\Controller;
use App\Http\Requests\InternalTribunal\AdminApplyInternalPenaltyRequest;
use App\Http\Requests\InternalTribunal\AdminUpdateInternalReportStatusRequest;
use App\Http\Resources\InternalTribunal\AdminInternalReportResource;
use App\Models\InternalPenalty;
use App\Models\InternalReport;
use App\Models\InternalReportEvidence;
use App\Services\InternalTribunal\InternalPenaltyService;
use App\Services\InternalTribunal\InternalReportEvidenceService;
use App\Services\InternalTribunal\InternalReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminInternalReportController extends Controller
{
    public function __construct(
        protected InternalReportService $reportService,
        protected InternalPenaltyService $penaltyService,
        protected InternalReportEvidenceService $evidenceService
    ) {
    }

    /**
     * List internal misconduct reports with filters for Super Admin.
     */
    public function index(Request $request): JsonResponse
    {
        // Bound list size and search length (pagination/scraping abuse).
        $request->validate(['search' => ['sometimes', 'nullable', 'string', 'max:100'], 'status' => ['sometimes', 'nullable', 'string', 'max:40'], 'category' => ['sometimes', 'nullable', 'string', 'max:60']]);

        $filters = [
            'status' => $request->query('status'),
            'category' => $request->query('category'),
            'search' => $request->query('search'),
        ];

        $reports = $this->reportService->getAdminReports($filters);

        return response()->json([
            'status' => true,
            'data' => AdminInternalReportResource::collection($reports),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * View detailed dossier for a misconduct report.
     */
    public function show(InternalReport $report): JsonResponse
    {
        $report->load([
            'reporter.profile',
            'reportedUser.profile',
            'reportedUser.juryPanel',
            'reviewer.profile',
            'evidence',
            'reviews.reviewer.profile',
            'penalties.applier.profile',
            'audits.performer.profile',
        ]);

        return response()->json([
            'status' => true,
            'data' => new AdminInternalReportResource($report),
        ]);
    }

    /**
     * Update the workflow status of an internal report.
     */
    public function updateStatus(AdminUpdateInternalReportStatusRequest $request, InternalReport $report): JsonResponse
    {
        $admin = $request->user();
        $validated = $request->validated();

        $updated = $this->reportService->updateStatus(
            $report,
            $validated['status'],
            $validated['notes'] ?? null,
            $validated['decision_reason'] ?? null,
            $admin
        );

        $updated->load([
            'reporter.profile',
            'reportedUser.profile',
            'reviewer.profile',
            'evidence',
            'reviews.reviewer.profile',
            'penalties.applier.profile',
            'audits.performer.profile',
        ]);

        return response()->json([
            'status' => true,
            'message' => "Report status successfully updated to {$validated['status']}.",
            'data' => new AdminInternalReportResource($updated),
        ]);
    }

    /**
     * Apply a disciplinary action/penalty to the reported user.
     */
    public function applyPenalty(AdminApplyInternalPenaltyRequest $request, InternalReport $report): JsonResponse
    {
        $admin = $request->user();
        $validated = $request->validated();

        $penalty = $this->penaltyService->applyPenalty(
            $report,
            $validated['action_type'],
            $validated['reason'],
            $validated['notes'] ?? null,
            $admin
        );

        $report->load([
            'reporter.profile',
            'reportedUser.profile',
            'reviewer.profile',
            'evidence',
            'reviews.reviewer.profile',
            'penalties.applier.profile',
            'audits.performer.profile',
        ]);

        return response()->json([
            'status' => true,
            'message' => "Action '{$validated['action_type']}' successfully applied and auditable.",
            'data' => [
                'penalty_id' => $penalty->id,
                'action_type' => $penalty->action_type?->value ?? $penalty->action_type,
                'report' => new AdminInternalReportResource($report),
            ],
        ]);
    }

    /**
     * Reverse a previously applied penalty.
     */
    public function reversePenalty(Request $request, InternalPenalty $penalty): JsonResponse
    {
        $request->validate([
            'reversal_reason' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $admin = $request->user();
        $reversed = $this->penaltyService->reversePenalty($penalty, $request->input('reversal_reason'), $admin);

        return response()->json([
            'status' => true,
            'message' => 'Penalty successfully reversed.',
            'data' => $reversed,
        ]);
    }

    /**
     * Download evidence attachment as Super Admin.
     */
    public function downloadEvidence(Request $request, InternalReportEvidence $evidence): StreamedResponse
    {
        return $this->evidenceService->downloadEvidence($evidence, $request->user());
    }
}
