<?php

namespace App\Http\Controllers\Tribunal;

use App\Enums\TribunalHearingEntryType;
use App\Http\Controllers\Controller;
use App\Models\TribunalCase;
use App\Models\TribunalHearing;
use App\Models\TribunalHearingEntry;
use App\Services\Tribunal\TribunalHearingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TribunalHearingController extends Controller
{
    public function __construct(
        protected TribunalHearingService $hearingService
    ) {
    }

    /**
     * Ensure the user is authorized to view the case.
     */
    protected function ensureAuthorized(TribunalCase $case, int $userId): void
    {
        if (!$case->isAuthorizedToView($userId)) {
            abort(403, 'Unauthorized. You do not have permission to access hearings for this case.');
        }
    }

    /**
     * List all hearings for a case.
     */
    public function index(TribunalCase $tribunalCase): JsonResponse
    {
        $userId = auth()->id();
        $this->ensureAuthorized($tribunalCase, $userId);

        $hearings = $tribunalCase->hearings()
            ->with(['juryPanel', 'participants.user.profile'])
            ->get();

        return response()->json([
            'hearings' => $hearings,
            'active_hearing' => $tribunalCase->activeHearing?->load([
                'participants.user.profile',
                'entries.sender.profile',
                'entries.relatedWitness',
                'entries.relatedEvidence',
            ]),
        ]);
    }

    /**
     * Show single hearing details, participants, witnesses, and entries.
     */
    public function show(TribunalHearing $hearing): JsonResponse
    {
        $userId = auth()->id();
        $this->ensureAuthorized($hearing->case, $userId);

        $hearing->load([
            'case.parties.user.profile',
            'juryPanel',
            'participants.user.profile',
            'witnesses',
            'entries.sender.profile',
            'entries.relatedWitness',
            'entries.relatedEvidence',
            'entries.responses.sender.profile',
        ]);

        return response()->json([
            'hearing' => $hearing,
            'user_role' => $hearing->case->getUserCaseRole($userId),
            'user_side' => $hearing->case->getUserCaseSide($userId),
        ]);
    }



    /**
     * Submit a statement or evidence reference in active hearing.
     */
    public function addEntry(Request $request, TribunalHearing $hearing): JsonResponse
    {
        $userId = auth()->id();
        $this->ensureAuthorized($hearing->case, $userId);

        $data = $request->validate([
            'entry_type' => 'required|string',
            'body' => 'required|string|max:5000',
            'related_evidence_id' => 'nullable|integer',
            'related_witness_id' => 'nullable|integer',
            'parent_entry_id' => 'nullable|integer',
        ]);

        $entry = $this->hearingService->addEntry($hearing, $userId, $data);

        return response()->json([
            'message' => 'Hearing entry submitted.',
            'entry' => $entry,
        ], 201);
    }

    /**
     * Respond to a specific jury question during hearing.
     */
    public function respondToQuestion(Request $request, TribunalHearing $hearing, TribunalHearingEntry $question): JsonResponse
    {
        $userId = auth()->id();
        $this->ensureAuthorized($hearing->case, $userId);

        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'related_evidence_id' => 'nullable|integer',
        ]);

        $data['entry_type'] = TribunalHearingEntryType::PartyAnswer->value;
        $data['parent_entry_id'] = $question->id;

        $entry = $this->hearingService->addEntry($hearing, $userId, $data);

        return response()->json([
            'message' => 'Response submitted.',
            'entry' => $entry,
        ], 201);
    }

}
