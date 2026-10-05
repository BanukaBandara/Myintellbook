<?php

namespace App\Http\Controllers\Tribunal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tribunal\ChallengeTribunalEvidenceRequest;
use App\Http\Requests\Tribunal\StoreTribunalEvidenceRequest;
use App\Http\Resources\Tribunal\TribunalEvidenceChallengeResource;
use App\Http\Resources\Tribunal\TribunalEvidenceResource;
use App\Models\TribunalCase;
use App\Models\TribunalEvidence;
use App\Services\Tribunal\TribunalEvidenceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TribunalEvidenceController extends Controller
{
    public function __construct(
        private readonly TribunalEvidenceService $evidenceService
    ) {
    }

    public function index(TribunalCase $tribunalCase): JsonResponse
    {
        $this->evidenceService->authorizeEvidenceAccess($tribunalCase, auth()->id());

        $evidenceList = $tribunalCase->evidence()
            ->with(['uploader.profile', 'challenges.challenger.profile'])
            ->latest('id')
            ->get();

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => TribunalEvidenceResource::collection($evidenceList),
        ]);
    }

    public function store(
        StoreTribunalEvidenceRequest $request,
        TribunalCase $tribunalCase
    ): JsonResponse {
        $file = $request->file('file');

        $evidence = $this->evidenceService->uploadEvidence(
            $tribunalCase,
            $request->validated(),
            $file,
            auth()->id()
        );

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => "Evidence {$evidence->evidence_number} uploaded successfully.",
            'data' => new TribunalEvidenceResource($evidence),
        ], 201);
    }

    public function show(
        TribunalCase $tribunalCase,
        TribunalEvidence $evidence
    ): JsonResponse {
        abort_if($evidence->tribunal_case_id !== $tribunalCase->id, 404, 'Evidence not found for this case.');

        $this->evidenceService->authorizeEvidenceAccess($tribunalCase, auth()->id());

        $evidence->load(['uploader.profile', 'challenges.challenger.profile']);

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => new TribunalEvidenceResource($evidence),
        ]);
    }

    public function download(
        TribunalCase $tribunalCase,
        TribunalEvidence $evidence
    ): StreamedResponse|BinaryFileResponse {
        abort_if($evidence->tribunal_case_id !== $tribunalCase->id, 404, 'Evidence not found for this case.');

        return $this->evidenceService->downloadEvidence(
            $tribunalCase,
            $evidence,
            auth()->id()
        );
    }

    public function challenge(
        ChallengeTribunalEvidenceRequest $request,
        TribunalCase $tribunalCase,
        TribunalEvidence $evidence
    ): JsonResponse {
        abort_if($evidence->tribunal_case_id !== $tribunalCase->id, 404, 'Evidence not found for this case.');

        $challenge = $this->evidenceService->challengeEvidence(
            $tribunalCase,
            $evidence,
            $request->validated('reason'),
            auth()->id()
        );

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => "Evidence challenge submitted for {$evidence->evidence_number}.",
            'data' => new TribunalEvidenceChallengeResource($challenge),
        ], 201);
    }
}
