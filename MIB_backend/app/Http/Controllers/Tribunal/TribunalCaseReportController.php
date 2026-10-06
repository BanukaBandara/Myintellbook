<?php

namespace App\Http\Controllers\Tribunal;

use App\Http\Controllers\Controller;
use App\Http\Resources\TribunalCaseReportResource;
use App\Models\TribunalCase;
use App\Models\TribunalCaseReport;
use App\Services\Tribunal\TribunalReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TribunalCaseReportController extends Controller
{
    public function __construct(
        protected TribunalReportService $reportService
    ) {}

    /**
     * List all official reports issued for a case.
     */
    public function index(TribunalCase $tribunalCase): JsonResponse
    {
        $userId = auth()->id();
        $this->reportService->authorizeUser($tribunalCase, $userId);

        $reports = $tribunalCase->reports()->with(['decision', 'case'])->get();
        $activeReport = $tribunalCase->activeReport;

        return response()->json([
            'case_id' => $tribunalCase->id,
            'case_number' => $tribunalCase->case_number,
            'reports' => TribunalCaseReportResource::collection($reports),
            'active_report' => $activeReport ? new TribunalCaseReportResource($activeReport) : null,
            'has_final_decision' => $tribunalCase->finalDecision()->exists(),
        ]);
    }

    /**
     * Generate or regenerate official final Tribunal report.
     */
    public function generateFinalReport(Request $request, TribunalCase $tribunalCase): JsonResponse
    {
        $userId = auth()->id();
        $forceRegenerate = $request->boolean('regenerate', false);

        $report = $this->reportService->generateFinalReport($tribunalCase, $userId, $forceRegenerate);

        return response()->json([
            'message' => $forceRegenerate
                ? 'Official Tribunal report regenerated successfully (superseding prior version).'
                : 'Official Tribunal report generated successfully.',
            'report' => new TribunalCaseReportResource($report),
        ], 201);
    }

    /**
     * View metadata of a specific report.
     */
    public function show(TribunalCaseReport $report): JsonResponse
    {
        $userId = auth()->id();
        $this->reportService->authorizeUser($report->case, $userId);

        return response()->json([
            'report' => new TribunalCaseReportResource($report->load(['case', 'decision'])),
        ]);
    }

    /**
     * Download the official report PDF file.
     */
    public function download(Request $request, TribunalCaseReport $report): BinaryFileResponse
    {
        $userId = auth()->id();
        return $this->reportService->downloadReport(
            $report,
            $userId,
            $request->ip(),
            $request->userAgent()
        );
    }

    /**
     * Public authenticity verification by code or report number.
     */
    public function verifyCode(string $verificationCode): JsonResponse
    {
        $result = $this->reportService->verifyCode($verificationCode);

        $status = $result['valid'] ? 200 : 404;
        return response()->json($result, $status);
    }

    /**
     * Verify authenticity by uploaded PDF file hash.
     */
    public function verifyFile(Request $request): JsonResponse
    {
        $request->validate([
            'report_file' => 'required|file|mimes:pdf|max:10240',
            'verification_code' => 'nullable|string|max:100',
        ]);

        $file = $request->file('report_file');
        $code = $request->input('verification_code');

        $result = $this->reportService->verifyFile($file, $code);

        $status = ($result['valid_hash'] ?? false) ? 200 : 422;
        return response()->json($result, $status);
    }
}
