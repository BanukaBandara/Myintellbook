<?php

namespace App\Http\Controllers\Tribunal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tribunal\AskAdjudicatorQuestionRequest;
use App\Http\Requests\Tribunal\PostProceduralNoticeRequest;
use App\Http\Requests\Tribunal\RespondToAdjudicatorQuestionRequest;
use App\Http\Requests\Tribunal\SendCaseRoomMessageRequest;
use App\Http\Resources\Tribunal\TribunalCaseMessageResource;
use App\Http\Resources\Tribunal\TribunalCaseRoomResource;
use App\Models\TribunalCase;
use App\Models\TribunalCaseMessage;
use App\Services\Tribunal\TribunalCaseRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TribunalCaseRoomController extends Controller
{
    public function __construct(
        protected TribunalCaseRoomService $caseRoomService
    ) {
    }

    /**
     * Get or lazy-provision the shared Case Room for the tribunal case.
     */
    public function show(Request $request, TribunalCase $tribunalCase): JsonResponse
    {
        $room = $this->caseRoomService->getOrCreateRoom($tribunalCase, $request->user()->id);

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => new TribunalCaseRoomResource($room),
        ]);
    }

    /**
     * List messages in the shared Case Room.
     */
    public function messages(Request $request, TribunalCase $tribunalCase): JsonResponse
    {
        // Bound list size and search length (pagination/scraping abuse).
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);

        $messages = $this->caseRoomService->getMessages(
            $tribunalCase,
            $request->user()->id,
            (int) $request->input('per_page', 50)
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => TribunalCaseMessageResource::collection($messages),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Send a normal case-room message.
     */
    public function sendMessage(
        SendCaseRoomMessageRequest $request,
        TribunalCase $tribunalCase
    ): JsonResponse {
        $message = $this->caseRoomService->sendMessage(
            $tribunalCase,
            $request->user()->id,
            $request->validated('body'),
            $request->validated('related_evidence_id')
        );

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Message posted to Case Room successfully.',
            'data' => new TribunalCaseMessageResource($message),
        ], 201);
    }

    /**
     * Post an adjudicator procedural notice.
     */
    public function postProceduralNotice(
        PostProceduralNoticeRequest $request,
        TribunalCase $tribunalCase
    ): JsonResponse {
        $notice = $this->caseRoomService->postProceduralNotice(
            $tribunalCase,
            $request->user()->id,
            $request->validated('body')
        );

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Procedural notice posted successfully.',
            'data' => new TribunalCaseMessageResource($notice),
        ], 201);
    }

    /**
     * Ask an adjudicator question.
     */
    public function askQuestion(
        AskAdjudicatorQuestionRequest $request,
        TribunalCase $tribunalCase
    ): JsonResponse {
        $question = $this->caseRoomService->askQuestion(
            $tribunalCase,
            $request->user()->id,
            $request->validated('body'),
            $request->validated('target_side')
        );

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Adjudicator question posted successfully.',
            'data' => new TribunalCaseMessageResource($question),
        ], 201);
    }

    /**
     * Respond to an adjudicator question.
     */
    public function respondToQuestion(
        RespondToAdjudicatorQuestionRequest $request,
        TribunalCase $tribunalCase,
        TribunalCaseMessage $question
    ): JsonResponse {
        $response = $this->caseRoomService->respondToQuestion(
            $tribunalCase,
            $question,
            $request->user()->id,
            $request->validated('body')
        );

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Response submitted successfully.',
            'data' => new TribunalCaseMessageResource($response),
        ], 201);
    }
}
