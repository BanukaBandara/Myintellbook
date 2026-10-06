<?php

namespace App\Http\Controllers\Tribunal;

use App\Http\Controllers\Controller;
use App\Http\Resources\TribunalDecisionResource;
use App\Models\TribunalCase;
use Illuminate\Http\JsonResponse;

class TribunalDecisionController extends Controller
{
    /**
     * View the formal published Tribunal decision for a case.
     * Before publication, returns safe pending state without exposing draft reasoning or notes.
     */
    public function show(TribunalCase $tribunalCase): JsonResponse
    {
        $userId = auth()->id();

        if (!$tribunalCase->isAuthorizedToView($userId)) {
            abort(403, 'Unauthorized. You do not have permission to access decisions for this case.');
        }

        $finalDecision = $tribunalCase->finalDecision;

        if (!$finalDecision) {
            return response()->json([
                'decision_pending' => true,
                'is_pending' => true,
                'data' => null,
                'message' => 'Tribunal decision is currently pending deliberation by the Jury Panel.',
                'case_id' => $tribunalCase->id,
                'case_number' => $tribunalCase->case_number,
                'status' => $tribunalCase->status->value,
            ]);
        }

        $resource = new TribunalDecisionResource($finalDecision);

        return response()->json([
            'decision_pending' => false,
            'is_pending' => false,
            'data' => $resource,
            'decision' => $resource,
        ]);
    }
}
