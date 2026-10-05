<?php

namespace App\Http\Controllers\Tribunal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tribunal\SendTribunalMessageRequest;
use App\Http\Resources\Tribunal\TribunalConversationResource;
use App\Http\Resources\Tribunal\TribunalMessageResource;
use App\Models\TribunalCase;
use App\Models\TribunalConversation;
use App\Services\Tribunal\TribunalConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TribunalConversationController extends Controller
{
    public function __construct(
        protected TribunalConversationService $conversationService
    ) {
    }

    /**
     * Get or initialize the confidential representation conversation for a case.
     */
    public function showForCase(Request $request, TribunalCase $tribunalCase): JsonResponse
    {
        $conversation = $this->conversationService->getOrCreateConversationForUser(
            $tribunalCase,
            $request->user()->id
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => new TribunalConversationResource($conversation),
        ]);
    }

    /**
     * List messages in a conversation.
     */
    public function messages(Request $request, TribunalConversation $conversation): JsonResponse
    {
        $messages = $this->conversationService->getMessages(
            $conversation,
            $request->user()->id,
            (int) $request->input('per_page', 50)
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => TribunalMessageResource::collection($messages),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Send a confidential message in a conversation.
     */
    public function sendMessage(
        SendTribunalMessageRequest $request,
        TribunalConversation $conversation
    ): JsonResponse {
        $message = $this->conversationService->sendMessage(
            $conversation,
            $request->user()->id,
            $request->validated('body')
        );

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Message sent.',
            'data' => new TribunalMessageResource($message),
        ], 201);
    }
}
