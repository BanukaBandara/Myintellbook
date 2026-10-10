<?php

namespace App\Http\Controllers\Tribunal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tribunal\DeclineRepresentationRequest;
use App\Http\Requests\Tribunal\EndRepresentationRequest;
use App\Http\Requests\Tribunal\RequestRepresentationRequest;
use App\Http\Resources\Tribunal\TribunalCaseResource;
use App\Http\Resources\Tribunal\TribunalRepresentationRequestResource;
use App\Http\Resources\Tribunal\TribunalRepresentativeAssignmentResource;
use App\Http\Resources\Tribunal\VerifiedRepresentativeResource;
use App\Models\TribunalCase;
use App\Models\TribunalRepresentationRequest;
use App\Services\Tribunal\TribunalRepresentationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TribunalRepresentationController extends Controller
{
    public function __construct(
        protected TribunalRepresentationService $representationService
    ) {
    }

    /**
     * List verified Attorneys-at-Law available for representation.
     */
    public function representatives(Request $request): JsonResponse
    {
        // Bound list size and search length (pagination/scraping abuse).
        $request->validate(['search' => ['sometimes', 'nullable', 'string', 'max:100'], 'case_id' => ['sometimes', 'nullable', 'integer', 'min:1']]);

        $case = null;
        if ($request->filled('case_id')) {
            $case = TribunalCase::find($request->input('case_id'));
        }

        $representatives = $this->representationService->getVerifiedRepresentatives(
            $case,
            $request->input('search')
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => VerifiedRepresentativeResource::collection($representatives),
        ]);
    }

    /**
     * Submit a representation request for a case.
     */
    public function storeRequest(
        RequestRepresentationRequest $request,
        TribunalCase $tribunalCase
    ): JsonResponse {
        $representationRequest = $this->representationService->requestRepresentation(
            $tribunalCase,
            auth()->id(),
            (int) $request->validated('representative_user_id'),
            $request->validated('message')
        );

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Representation request submitted successfully.',
            'data' => new TribunalRepresentationRequestResource($representationRequest),
        ], 201);
    }

    /**
     * List representation requests received by the authenticated attorney.
     */
    public function lawyerRequests(Request $request): JsonResponse
    {
        // Bound list size and search length (pagination/scraping abuse).
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'status' => ['sometimes', 'nullable', 'string', 'max:40']]);

        abort_unless(
            $request->user()->canActAsLegalRepresentative(),
            403,
            'Only verified Attorneys-at-Law can access the representation request inbox.'
        );

        $requests = $this->representationService->getLawyerRequests(
            $request->user()->id,
            $request->input('status'),
            (int) $request->input('per_page', 15)
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => TribunalRepresentationRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Accept a representation request.
     */
    public function accept(
        Request $request,
        TribunalRepresentationRequest $representationRequest
    ): JsonResponse {
        $assignment = $this->representationService->acceptRequest(
            $representationRequest,
            $request->user()
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Representation request accepted successfully.',
            'data' => new TribunalRepresentativeAssignmentResource($assignment),
        ]);
    }

    /**
     * Decline a representation request.
     */
    public function decline(
        DeclineRepresentationRequest $request,
        TribunalRepresentationRequest $representationRequest
    ): JsonResponse {
        $updatedRequest = $this->representationService->declineRequest(
            $representationRequest,
            $request->user(),
            $request->validated('reason')
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Representation request declined.',
            'data' => new TribunalRepresentationRequestResource($updatedRequest),
        ]);
    }

    /**
     * End active representation for a case.
     */
    public function end(
        EndRepresentationRequest $request,
        TribunalCase $tribunalCase
    ): JsonResponse {
        $assignment = $this->representationService->endRepresentation(
            $tribunalCase,
            $request->user(),
            $request->validated('reason')
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Legal representation has concluded.',
            'data' => new TribunalRepresentativeAssignmentResource($assignment),
        ]);
    }

    /**
     * List all cases actively represented by the authenticated attorney.
     */
    public function representedCases(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->canActAsLegalRepresentative(),
            403,
            'Only verified Attorneys-at-Law can access represented cases.'
        );

        $cases = $this->representationService->getRepresentedCases($request->user()->id);

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => TribunalCaseResource::collection($cases),
        ]);
    }

    /**
     * Get representation status for the current user on a specific case.
     */
    public function caseRepresentation(Request $request, TribunalCase $tribunalCase): JsonResponse
    {
        $userId = $request->user()->id;

        // Active assignment as client
        $assignment = $tribunalCase->activeRepresentativeAssignments()
            ->where('client_user_id', $userId)
            ->with(['representative.profile', 'representative.latestProfessionalVerification'])
            ->first();

        // Active assignment as representative
        if (!$assignment) {
            $assignment = $tribunalCase->activeRepresentativeAssignments()
                ->where('representative_user_id', $userId)
                ->with(['client.profile', 'representative.profile'])
                ->first();
        }

        // Pending request as client
        $pendingRequest = $tribunalCase->representationRequests()
            ->where('client_user_id', $userId)
            ->where('status', \App\Enums\TribunalRepresentationRequestStatus::Pending)
            ->with(['representative.profile', 'representative.latestProfessionalVerification'])
            ->latest('id')
            ->first();

        // Latest declined request as client
        $declinedRequest = $tribunalCase->representationRequests()
            ->where('client_user_id', $userId)
            ->where('status', \App\Enums\TribunalRepresentationRequestStatus::Declined)
            ->with(['representative.profile'])
            ->latest('id')
            ->first();

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => [
                'has_active_representation' => $assignment !== null,
                'active_assignment' => $assignment ? new TribunalRepresentativeAssignmentResource($assignment) : null,
                'pending_request' => $pendingRequest ? new TribunalRepresentationRequestResource($pendingRequest) : null,
                'declined_request' => (!$assignment && !$pendingRequest && $declinedRequest) ? new TribunalRepresentationRequestResource($declinedRequest) : null,
                'is_representative' => $tribunalCase->isAcceptedRepresentative($userId),
            ],
        ]);
    }
}
