<?php

namespace App\Http\Controllers\Jury;

use App\Enums\TribunalHearingEntryType;
use App\Http\Controllers\Controller;
use App\Models\TribunalCase;
use App\Models\TribunalHearing;
use App\Models\TribunalWitness;
use App\Services\Tribunal\TribunalHearingService;
use App\Services\Tribunal\TribunalWitnessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JuryHearingController extends Controller
{
    public function __construct(
        protected TribunalHearingService $hearingService,
        protected TribunalWitnessService $witnessService
    ) {
    }

    /**
     * List all hearings for an assigned case.
     */
    public function index(int $caseId): JsonResponse
    {
        $case = TribunalCase::findOrFail($caseId);
        $user = auth()->user();

        if (!$case->isAssignedJuryPanelUser($user->id)) {
            abort(403, 'Unauthorized. Case is not assigned to your active Jury Panel.');
        }

        $hearings = $case->hearings()
            ->with(['juryPanel', 'participants.user.profile'])
            ->get();

        return response()->json([
            'hearings' => $hearings,
            'active_hearing' => $case->activeHearing?->load(['participants.user.profile', 'entries.sender.profile', 'entries.relatedWitness', 'entries.relatedEvidence']),
        ]);
    }

    /**
     * Schedule a hearing for the assigned case.
     */
    public function schedule(Request $request, int $caseId): JsonResponse
    {
        $case = TribunalCase::findOrFail($caseId);
        $user = auth()->user();

        $data = $request->validate([
            'hearing_type' => 'nullable|string|in:formal,preliminary,continuation',
            'scheduled_at' => 'nullable|date',
            'location_type' => 'nullable|string|in:online,physical,hybrid',
            'meeting_link' => 'nullable|string|url|max:500',
            'notes' => 'nullable|string|max:2000',
        ]);

        $hearing = $this->hearingService->scheduleHearing($case, $user->id, $data);

        return response()->json([
            'message' => 'Hearing successfully scheduled.',
            'hearing' => $hearing,
        ], 201);
    }

    /**
     * Show single hearing details, participants, witnesses, and entries.
     */
    public function show(int $hearingId): JsonResponse
    {
        $hearing = TribunalHearing::with([
            'case.parties.user.profile',
            'juryPanel',
            'participants.user.profile',
            'witnesses',
            'entries.sender.profile',
            'entries.relatedWitness',
            'entries.relatedEvidence',
            'entries.responses.sender.profile',
        ])->findOrFail($hearingId);

        $case = $hearing->case;
        $user = auth()->user();

        if (!$case->isAssignedJuryPanelUser($user->id)) {
            abort(403, 'Unauthorized. You are not assigned to this case.');
        }

        return response()->json([
            'hearing' => $hearing,
        ]);
    }

    /**
     * Start scheduled hearing.
     */
    public function start(int $hearingId): JsonResponse
    {
        $hearing = TribunalHearing::findOrFail($hearingId);
        $user = auth()->user();

        $started = $this->hearingService->startHearing($hearing, $user->id);

        return response()->json([
            'message' => 'Hearing has been started.',
            'hearing' => $started,
        ]);
    }

    /**
     * Recess active hearing.
     */
    public function recess(int $hearingId): JsonResponse
    {
        $hearing = TribunalHearing::findOrFail($hearingId);
        $user = auth()->user();

        $recessed = $this->hearingService->recessHearing($hearing, $user->id);

        return response()->json([
            'message' => 'Hearing has been recessed.',
            'hearing' => $recessed,
        ]);
    }

    /**
     * Resume recessed hearing.
     */
    public function resume(int $hearingId): JsonResponse
    {
        $hearing = TribunalHearing::findOrFail($hearingId);
        $user = auth()->user();

        $resumed = $this->hearingService->resumeHearing($hearing, $user->id);

        return response()->json([
            'message' => 'Hearing has resumed.',
            'hearing' => $resumed,
        ]);
    }

    /**
     * Close hearing and move case to deliberation.
     */
    public function close(int $hearingId): JsonResponse
    {
        $hearing = TribunalHearing::findOrFail($hearingId);
        $user = auth()->user();

        $closed = $this->hearingService->closeHearing($hearing, $user->id);

        return response()->json([
            'message' => 'Hearing has been closed. Case moved to deliberation.',
            'hearing' => $closed,
        ]);
    }

    /**
     * List witnesses for assigned case.
     */
    public function witnesses(int $caseId): JsonResponse
    {
        $case = TribunalCase::findOrFail($caseId);
        $user = auth()->user();

        if (!$case->isAssignedJuryPanelUser($user->id)) {
            abort(403, 'Unauthorized.');
        }

        $witnesses = $case->witnesses()
            ->with(['proposer.profile', 'witnessUser.profile'])
            ->get();

        return response()->json([
            'witnesses' => $witnesses,
        ]);
    }

    /**
     * Approve proposed witness.
     */
    public function approveWitness(int $caseId, int $witnessId): JsonResponse
    {
        $witness = TribunalWitness::where('tribunal_case_id', $caseId)->findOrFail($witnessId);
        $user = auth()->user();

        $approved = $this->witnessService->approveWitness($witness, $user->id);

        return response()->json([
            'message' => 'Witness approved.',
            'witness' => $approved,
        ]);
    }

    /**
     * Reject proposed witness with reason.
     */
    public function rejectWitness(Request $request, int $caseId, int $witnessId): JsonResponse
    {
        $witness = TribunalWitness::where('tribunal_case_id', $caseId)->findOrFail($witnessId);
        $user = auth()->user();

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $rejected = $this->witnessService->rejectWitness($witness, $user->id, $data['reason']);

        return response()->json([
            'message' => 'Witness rejected.',
            'witness' => $rejected,
        ]);
    }

    /**
     * Post a procedural direction or entry from Jury Panel.
     */
    public function addEntry(Request $request, int $hearingId): JsonResponse
    {
        $hearing = TribunalHearing::findOrFail($hearingId);
        $user = auth()->user();

        $data = $request->validate([
            'entry_type' => 'required|string',
            'body' => 'required|string|max:5000',
            'related_evidence_id' => 'nullable|integer',
            'related_witness_id' => 'nullable|integer',
            'parent_entry_id' => 'nullable|integer',
        ]);

        $entry = $this->hearingService->addEntry($hearing, $user->id, $data);

        return response()->json([
            'message' => 'Hearing entry recorded.',
            'entry' => $entry,
        ], 201);
    }

    /**
     * Jury Panel asks a hearing question.
     */
    public function askQuestion(Request $request, int $hearingId): JsonResponse
    {
        $hearing = TribunalHearing::findOrFail($hearingId);
        $user = auth()->user();

        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'target_side' => 'nullable|string|in:complainant,respondent,both,witness',
            'related_witness_id' => 'nullable|integer',
            'related_evidence_id' => 'nullable|integer',
        ]);

        $data['entry_type'] = TribunalHearingEntryType::JuryQuestion->value;

        $entry = $this->hearingService->addEntry($hearing, $user->id, $data);

        return response()->json([
            'message' => 'Hearing question posted.',
            'entry' => $entry,
        ], 201);
    }

    /**
     * Record witness testimony during active hearing.
     */
    public function recordTestimony(Request $request, int $hearingId, int $witnessId): JsonResponse
    {
        $hearing = TribunalHearing::findOrFail($hearingId);
        $witness = TribunalWitness::findOrFail($witnessId);
        $user = auth()->user();

        $data = $request->validate([
            'testimony' => 'required|string|max:10000',
        ]);

        $entry = $this->witnessService->recordTestimony($hearing, $witness, $user->id, $data['testimony']);

        return response()->json([
            'message' => 'Witness testimony recorded.',
            'entry' => $entry,
        ], 201);
    }
}
