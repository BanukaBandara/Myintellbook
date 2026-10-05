<?php

namespace App\Http\Controllers\Tribunal;

use App\Enums\TribunalJuryAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tribunal\DeclareJurorConflictRequest;
use App\Http\Resources\Tribunal\TribunalJuryAssignmentResource;
use App\Models\TribunalCase;
use App\Models\TribunalJuryAssignment;
use App\Services\Tribunal\JurySelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TribunalJuryController extends Controller
{
    public function __construct(
        private readonly JurySelectionService $jurySelectionService
    ) {
    }

    /**
     * Get pending and active assignments for the current authenticated juror.
     */
    public function jurorCases(): JsonResponse
    {
        $userId = auth()->id();

        $pending = TribunalJuryAssignment::query()
            ->where('juror_id', $userId)
            ->where('status', TribunalJuryAssignmentStatus::Invited)
            ->with(['case.parties.user.profile'])
            ->latest('assigned_at')
            ->get();

        $active = TribunalJuryAssignment::query()
            ->where('juror_id', $userId)
            ->where('status', TribunalJuryAssignmentStatus::Accepted)
            ->with(['case.parties.user.profile'])
            ->latest('assigned_at')
            ->get();

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => [
                'pending' => TribunalJuryAssignmentResource::collection($pending),
                'active' => TribunalJuryAssignmentResource::collection($active),
            ],
        ]);
    }

    /**
     * Trigger jury selection for a case.
     */
    public function select(TribunalCase $tribunalCase): JsonResponse
    {
        $assignment = $this->jurySelectionService->assignJurorToCase($tribunalCase);

        if (!$assignment) {
            return response()->json([
                'code' => 200,
                'status' => false,
                'message' => 'No eligible jurors are currently available. Case remains awaiting assignment.',
                'data' => null,
            ]);
        }

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Juror successfully selected and assigned.',
            'data' => new TribunalJuryAssignmentResource($assignment),
        ]);
    }

    /**
     * Juror declares conflict or no conflict.
     */
    public function declareConflict(
        DeclareJurorConflictRequest $request,
        TribunalCase $tribunalCase
    ): JsonResponse {
        $hasConflict = (bool) $request->validated('has_conflict');
        $conflictReason = $request->validated('conflict_reason');

        $assignment = $this->jurySelectionService->declareConflict(
            $tribunalCase,
            auth()->id(),
            $hasConflict,
            $conflictReason
        );

        $message = $hasConflict
            ? 'Conflict of interest declared. You have been recused and a replacement will be selected.'
            : 'No conflict declared. Jury assignment accepted successfully.';

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => $message,
            'data' => new TribunalJuryAssignmentResource($assignment),
        ]);
    }

    /**
     * Juror accepts assignment.
     */
    public function accept(TribunalCase $tribunalCase): JsonResponse
    {
        $assignment = $this->jurySelectionService->acceptAssignment(
            $tribunalCase,
            auth()->id()
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Jury assignment accepted successfully.',
            'data' => new TribunalJuryAssignmentResource($assignment),
        ]);
    }

    /**
     * Juror recuses from assignment.
     */
    public function recuse(
        Request $request,
        TribunalCase $tribunalCase
    ): JsonResponse {
        $validated = $request->validate([
            'recusal_reason' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $assignment = $this->jurySelectionService->recuseAssignment(
            $tribunalCase,
            auth()->id(),
            $validated['recusal_reason']
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'You have recused yourself from this case.',
            'data' => new TribunalJuryAssignmentResource($assignment),
        ]);
    }
}
