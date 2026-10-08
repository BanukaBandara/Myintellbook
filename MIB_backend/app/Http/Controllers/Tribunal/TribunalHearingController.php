<?php

namespace App\Http\Controllers\Tribunal;

use App\Enums\TribunalHearingEntryType;
use App\Http\Controllers\Controller;
use App\Models\TribunalCase;
use App\Models\TribunalHearing;
use App\Models\TribunalHearingEntry;
use App\Models\TribunalWitness;
use App\Services\Tribunal\TribunalHearingService;
use App\Services\Tribunal\TribunalWitnessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TribunalHearingController extends Controller
{
    public function __construct(
        protected TribunalHearingService $hearingService,
        protected TribunalWitnessService $witnessService
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
     * Propose a witness for a case.
     * Proposing side is derived on backend.
     */
    public function proposeWitness(Request $request, TribunalCase $tribunalCase): JsonResponse
    {
        $userId = auth()->id();
        $this->ensureAuthorized($tribunalCase, $userId);

        $data = $request->validate([
            'witness_name' => 'required|string|max:255',
            'witness_email' => 'nullable|email|max:255',
            'relationship_to_case' => 'nullable|string|max:500',
            'statement_summary' => 'nullable|string|max:2000',
            'witness_user_id' => 'nullable|integer|exists:users,id',
            'tribunal_hearing_id' => 'nullable|integer|exists:tribunal_hearings,id',
        ]);

        $witness = $this->witnessService->proposeWitness($tribunalCase, $userId, $data);

        return response()->json([
            'message' => 'Witness successfully proposed.',
            'witness' => $witness,
        ], 201);
    }

    /**
     * List witnesses for a case.
     */
    public function witnesses(TribunalCase $tribunalCase): JsonResponse
    {
        $userId = auth()->id();
        $this->ensureAuthorized($tribunalCase, $userId);

        $witnesses = $tribunalCase->witnesses()
            ->with(['proposer.profile', 'witnessUser.profile'])
            ->get();

        return response()->json([
            'witnesses' => $witnesses,
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

    /**
     * Record approved witness testimony.
     */
    public function recordTestimony(Request $request, TribunalHearing $hearing, TribunalWitness $witness): JsonResponse
    {
        $userId = auth()->id();
        $this->ensureAuthorized($hearing->case, $userId);

        $data = $request->validate([
            'testimony' => 'required|string|max:10000',
        ]);

        $entry = $this->witnessService->recordTestimony($hearing, $witness, $userId, $data['testimony']);

        return response()->json([
            'message' => 'Witness testimony recorded.',
            'entry' => $entry,
        ], 201);
    }
}
