<?php

namespace App\Http\Controllers\InternalTribunal;

use App\Http\Controllers\Controller;
use App\Http\Requests\InternalTribunal\StoreInternalReportRequest;
use App\Http\Resources\InternalTribunal\InternalReportResource;
use App\Models\InternalReport;
use App\Models\InternalReportEvidence;
use App\Services\InternalTribunal\InternalReportEvidenceService;
use App\Services\InternalTribunal\InternalReportService;
use App\Services\InternalTribunal\InternalReportUserSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InternalReportController extends Controller
{
    public function __construct(
        protected InternalReportService $reportService,
        protected InternalReportUserSearchService $searchService,
        protected InternalReportEvidenceService $evidenceService
    ) {
    }

    /**
     * Search platform users eligible for internal misconduct reporting.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        // Bound list size and search length (pagination/scraping abuse).
        $request->validate(['q' => ['sometimes', 'nullable', 'string', 'max:100']]);

        $query = (string) $request->input('q', '');
        $currentUserId = (int) $request->user()->id;

        $users = $this->searchService->search($query, $currentUserId);

        $results = $users->map(function ($u) {
            $profile = $u->profile;
            $name = $profile && ($profile->first_name || $profile->last_name)
                ? trim("{$profile->first_name} {$profile->last_name}")
                : "User #{$u->id}";

            $profilePhotoUrl = null;
            if ($profile && !empty($profile->profile_image)) {
                $raw = $profile->profile_image;
                if (str_starts_with($raw, 'data:image') || str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
                    $profilePhotoUrl = $raw;
                } elseif (str_starts_with($raw, '/')) {
                    $profilePhotoUrl = url($raw);
                } else {
                    $profilePhotoUrl = asset('storage/' . $raw);
                }
            }

            return [
                'id' => $u->id,
                'name' => $name,
                'username' => $profile?->slug ? ltrim($profile->slug, '@') : "user{$u->id}",
                'profile_image' => $profilePhotoUrl,
                'is_jury_panel' => $u->juryPanel !== null,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $results,
        ]);
    }

    /**
     * Submit an internal misconduct report.
     */
    public function store(StoreInternalReportRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $evidenceFiles = $request->file('evidence', []);

        $report = $this->reportService->createReport($validated, $evidenceFiles, $user);

        return response()->json([
            'status' => true,
            'message' => 'Internal misconduct report submitted successfully.',
            'data' => new InternalReportResource($report),
        ], 201);
    }

    /**
     * List current user's submitted reports.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $reports = $this->reportService->getUserReports($user->id);

        return response()->json([
            'status' => true,
            'data' => InternalReportResource::collection($reports),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * View a specific report submitted by the current user.
     * Reporter can only view their own report. Reported user cannot view it.
     */
    public function show(Request $request, InternalReport $report): JsonResponse
    {
        $user = $request->user();

        if ((int) $report->reporter_user_id !== (int) $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden. You do not have permission to view this report.',
            ], 403);
        }

        $report->load(['reportedUser.profile', 'evidence']);

        return response()->json([
            'status' => true,
            'data' => new InternalReportResource($report),
        ]);
    }

    /**
     * Download evidence attachment.
     */
    public function downloadEvidence(Request $request, InternalReportEvidence $evidence): StreamedResponse
    {
        return $this->evidenceService->downloadEvidence($evidence, $request->user());
    }
}
