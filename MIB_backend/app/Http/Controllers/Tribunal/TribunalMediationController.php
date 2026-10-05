<?php

namespace App\Http\Controllers\Tribunal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tribunal\CounterSettlementProposalRequest;
use App\Http\Requests\Tribunal\CreateSettlementProposalRequest;
use App\Http\Requests\Tribunal\EndMediationRequest;
use App\Http\Requests\Tribunal\RespondToMediationRequest;
use App\Http\Resources\Tribunal\TribunalMediationResource;
use App\Http\Resources\Tribunal\TribunalSettlementAgreementResource;
use App\Http\Resources\Tribunal\TribunalSettlementProposalResource;
use App\Models\TribunalCase;
use App\Models\TribunalMediation;
use App\Models\TribunalSettlementAgreement;
use App\Models\TribunalSettlementProposal;
use App\Services\Tribunal\TribunalCaseRoomService;
use App\Services\Tribunal\TribunalMediationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TribunalMediationController extends Controller
{
    public function __construct(
        protected TribunalMediationService $mediationService,
        protected TribunalCaseRoomService $caseRoomService
    ) {
    }

    /**
     * Get the current or latest mediation process for the tribunal case.
     */
    public function showForCase(Request $request, TribunalCase $tribunalCase): JsonResponse
    {
        $this->caseRoomService->ensureAuthorized($tribunalCase, $request->user()->id);

        $mediation = $tribunalCase->mediations()
            ->with([
                'consents.user.profile',
                'proposals.proposer.profile',
                'proposals.acceptances',
                'settlementAgreement',
            ])
            ->latest()
            ->first();

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => $mediation ? new TribunalMediationResource($mediation) : null,
        ]);
    }

    /**
     * Request mediation (Principal complainant or respondent).
     */
    public function requestMediation(Request $request, TribunalCase $tribunalCase): JsonResponse
    {
        $this->caseRoomService->ensureAuthorized($tribunalCase, $request->user()->id);

        $mediation = $this->mediationService->requestMediation($tribunalCase, $request->user()->id);

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Mediation requested successfully. Awaiting opposing party consent.',
            'data' => new TribunalMediationResource($mediation),
        ], 201);
    }

    /**
     * Offer mediation (Active Adjudicator).
     */
    public function offerMediation(Request $request, TribunalCase $tribunalCase): JsonResponse
    {
        $this->caseRoomService->ensureAuthorized($tribunalCase, $request->user()->id);

        $mediation = $this->mediationService->offerMediation($tribunalCase, $request->user()->id);

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Mediation offered to both parties successfully.',
            'data' => new TribunalMediationResource($mediation),
        ], 201);
    }

    /**
     * Accept or decline mediation offer (Principal party only).
     */
    public function respond(RespondToMediationRequest $request, TribunalMediation $mediation): JsonResponse
    {
        $case = $mediation->tribunalCase;
        $this->caseRoomService->ensureAuthorized($case, $request->user()->id);

        $updated = $this->mediationService->respondToMediation(
            $mediation,
            $request->user()->id,
            $request->validated('response')
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Mediation response recorded successfully.',
            'data' => new TribunalMediationResource($updated),
        ]);
    }

    /**
     * Conclude / end mediation as failed (Adjudicator or principal party).
     */
    public function endMediation(EndMediationRequest $request, TribunalMediation $mediation): JsonResponse
    {
        $case = $mediation->tribunalCase;
        $this->caseRoomService->ensureAuthorized($case, $request->user()->id);

        $ended = $this->mediationService->endMediation(
            $mediation,
            $request->user()->id,
            $request->validated('reason')
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Mediation ended. Case returned to prior procedural status.',
            'data' => new TribunalMediationResource($ended),
        ]);
    }

    /**
     * Submit a settlement proposal (Principal party only).
     */
    public function createProposal(CreateSettlementProposalRequest $request, TribunalMediation $mediation): JsonResponse
    {
        $case = $mediation->tribunalCase;
        $this->caseRoomService->ensureAuthorized($case, $request->user()->id);

        $proposal = $this->mediationService->createProposal(
            $mediation,
            $request->user()->id,
            $request->validated('terms')
        );

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Settlement proposal submitted successfully.',
            'data' => new TribunalSettlementProposalResource($proposal),
        ], 201);
    }

    /**
     * Submit a counter-proposal (Principal opposing party only).
     */
    public function counterProposal(
        CounterSettlementProposalRequest $request,
        TribunalMediation $mediation,
        TribunalSettlementProposal $proposal
    ): JsonResponse {
        $case = $mediation->tribunalCase;
        $this->caseRoomService->ensureAuthorized($case, $request->user()->id);

        $counter = $this->mediationService->counterProposal(
            $mediation,
            $proposal,
            $request->user()->id,
            $request->validated('terms')
        );

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Counter-proposal submitted successfully.',
            'data' => new TribunalSettlementProposalResource($counter),
        ], 201);
    }

    /**
     * Explicitly accept a settlement proposal (Principal party only).
     */
    public function acceptProposal(Request $request, TribunalSettlementProposal $proposal): JsonResponse
    {
        $mediation = $proposal->mediation;
        $case = $mediation->tribunalCase;
        $this->caseRoomService->ensureAuthorized($case, $request->user()->id);

        $result = $this->mediationService->acceptProposal($proposal, $request->user()->id);

        if ($result instanceof TribunalSettlementAgreement) {
            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'Settlement finalized! Both parties have accepted the terms.',
                'data' => [
                    'settled' => true,
                    'agreement' => new TribunalSettlementAgreementResource($result),
                ],
            ]);
        }

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Settlement proposal accepted. Awaiting final acceptance from opposing party.',
            'data' => [
                'settled' => false,
                'proposal' => new TribunalSettlementProposalResource($result),
            ],
        ]);
    }

    /**
     * Reject a settlement proposal (Principal opposing party only).
     */
    public function rejectProposal(Request $request, TribunalSettlementProposal $proposal): JsonResponse
    {
        $mediation = $proposal->mediation;
        $case = $mediation->tribunalCase;
        $this->caseRoomService->ensureAuthorized($case, $request->user()->id);

        $rejected = $this->mediationService->rejectProposal($proposal, $request->user()->id);

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Settlement proposal rejected.',
            'data' => new TribunalSettlementProposalResource($rejected),
        ]);
    }
}
