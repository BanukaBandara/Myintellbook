<?php

namespace App\Http\Controllers\Tribunal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tribunal\StoreTribunalCaseRequest;
use App\Http\Requests\Tribunal\SubmitTribunalResponseRequest;
use App\Http\Resources\TribunalCaseResource;
use App\Models\TribunalCase;
use App\Services\Tribunal\TribunalCaseService;
use App\Services\Tribunal\TribunalRespondentService;
use Illuminate\Http\JsonResponse;

class TribunalCaseController extends Controller
{
    public function __construct(
        private readonly TribunalCaseService $tribunalCaseService,
        private readonly TribunalRespondentService $tribunalRespondentService
    ) {
    }

    public function store(
        StoreTribunalCaseRequest $request
    ): JsonResponse {
        $case = $this->tribunalCaseService->create(
            $request->validated(),
            auth()->id()
        );

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Tribunal case submitted successfully.',
            'data' => new TribunalCaseResource($case),
        ], 201);
    }

    public function index(): JsonResponse
    {
        $cases = TribunalCase::query()
            ->with([
                'creator.profile',
                'parties.user.profile',
                'response',
            ])
            ->whereHas('parties', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->latest()
            ->get();

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => TribunalCaseResource::collection($cases),
        ]);
    }

    public function show(
        TribunalCase $tribunalCase
    ): JsonResponse {
        $isParticipant = $tribunalCase->isParticipant(auth()->id());
        $isAcceptedJuror = $tribunalCase->isAcceptedJuror(auth()->id());
        $isAcceptedRepresentative = $tribunalCase->isAcceptedRepresentative(auth()->id());

        abort_unless($isParticipant || $isAcceptedJuror || $isAcceptedRepresentative, 403, 'You are not authorized to view this tribunal case.');

        $tribunalCase->load([
            'creator.profile',
            'parties.user.profile',
            'response',
            'evidence.uploader.profile',
            'evidence.challenges.challenger.profile',
            'acceptedJuryAssignment.juror.profile',
            'currentJuryAssignment',
            'activeRepresentativeAssignments.representative.profile',
            'activeRepresentativeAssignments.client.profile',
            'representationRequests.representative.profile',
        ]);

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => new TribunalCaseResource($tribunalCase),
        ]);
    }

    public function acknowledge(
        TribunalCase $tribunalCase
    ): JsonResponse {
        $case = $this->tribunalRespondentService->acknowledge(
            $tribunalCase,
            auth()->id()
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Tribunal case acknowledged successfully.',
            'data' => new TribunalCaseResource($case),
        ]);
    }

    public function submitResponse(
        SubmitTribunalResponseRequest $request,
        TribunalCase $tribunalCase
    ): JsonResponse {
        $case = $this->tribunalRespondentService->submitResponse(
            $tribunalCase,
            $request->validated(),
            auth()->id()
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Tribunal case response submitted successfully.',
            'data' => new TribunalCaseResource($case),
        ]);
    }
}
