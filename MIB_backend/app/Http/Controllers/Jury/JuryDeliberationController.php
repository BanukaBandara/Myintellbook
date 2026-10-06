<?php

namespace App\Http\Controllers\Jury;

use App\Http\Controllers\Controller;
use App\Models\TribunalCase;
use App\Models\TribunalDecisionOrder;
use App\Models\TribunalDeliberationNote;
use App\Models\TribunalFinding;
use App\Services\Tribunal\TribunalDecisionService;
use App\Services\Tribunal\TribunalDeliberationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JuryDeliberationController extends Controller
{
    public function __construct(
        protected TribunalDeliberationService $deliberationService,
        protected TribunalDecisionService $decisionService
    ) {
    }

    /**
     * Get or initialize deliberation dossier (notes, findings, decision draft).
     */
    public function show(int $caseId): JsonResponse
    {
        $case = TribunalCase::with([
            'evidence',
            'hearings.entries.sender.profile',
            'hearings.entries.relatedWitness',
            'hearings.entries.relatedEvidence',
            'witnesses',
            'parties.user.profile',
        ])->findOrFail($caseId);
        $user = auth()->user();

        $deliberation = $this->deliberationService->getOrCreateDeliberation($case, $user->id);
        $decision = $this->decisionService->getOrCreateDraftDecision($case, $user->id);

        $findings = $case->findings()->with(['evidence', 'witnesses', 'hearingEntries'])->get();
        $notes = $deliberation->notes()->with('author.profile')->get();

        $latestHearing = $case->hearings()->latest()->first();

        $dossier = [
            'case' => [
                'id' => $case->id,
                'case_number' => $case->case_number,
                'title' => $case->title,
                'category' => $case->category,
                'description' => $case->description,
                'requested_resolution' => $case->requested_resolution,
                'status' => $case->status->value,
                'complainant' => [
                    'id' => $case->complainantUser?->id ?? 0,
                    'name' => $case->complainantUser?->name ?? 'Complainant',
                ],
                'respondent' => [
                    'id' => $case->respondentUser?->id ?? 0,
                    'name' => $case->respondentUser?->name ?? 'Respondent',
                ],
            ],
            'response' => $case->responses()->latest()->first(),
            'evidence' => $case->evidence->map(fn ($e) => [
                'id' => $e->id,
                'evidence_number' => $e->evidence_number,
                'title' => $e->title,
                'type' => $e->type?->value ?? (string) $e->type,
                'status' => $e->status?->value ?? (string) $e->status,
                'challenge_status' => $e->challenges()->exists() ? 'challenged' : 'unchallenged',
                'is_flagged' => false,
                'created_at' => $e->created_at?->toIso8601String(),
            ]),
            'witnesses' => $case->witnesses->map(fn ($w) => [
                'id' => $w->id,
                'witness_name' => $w->witness_name,
                'side' => $w->side?->value ?? (string) $w->side,
                'relationship_to_case' => $w->relationship_to_case,
                'statement_summary' => $w->statement_summary,
                'status' => $w->status?->value ?? (string) $w->status,
            ]),
            'hearing' => $latestHearing ? [
                'id' => $latestHearing->id,
                'hearing_number' => $latestHearing->hearing_number,
                'status' => $latestHearing->status?->value ?? (string) $latestHearing->status,
                'started_at' => $latestHearing->started_at?->toIso8601String(),
                'ended_at' => $latestHearing->ended_at?->toIso8601String(),
            ] : null,
            'hearing_entries' => $latestHearing ? $latestHearing->entries->map(fn ($h) => [
                'id' => $h->id,
                'sequence_number' => $h->sequence_number,
                'entry_type' => $h->entry_type?->value ?? (string) $h->entry_type,
                'sender_name' => $h->sender?->name ?? 'Panel',
                'side' => $h->side,
                'body' => $h->body,
                'created_at' => $h->created_at?->toIso8601String(),
            ]) : [],
        ];

        $payload = [
            'deliberation' => $deliberation,
            'decision' => $decision->load(['juryPanel', 'orders']),
            'findings' => $findings,
            'notes' => $notes,
            'orders' => $decision->orders,
            'dossier' => $dossier,
            'case_summary' => [
                'id' => $case->id,
                'case_number' => $case->case_number,
                'title' => $case->title,
                'status' => $case->status->value,
                'evidence_count' => $case->evidence->count(),
                'witness_count' => $case->witnesses->count(),
                'hearings_completed' => $case->hearings()->where('status', 'completed')->count(),
            ],
        ];

        return response()->json([
            'status' => true,
            'data' => $payload,
            ...$payload,
        ]);
    }

    /**
     * Add a private deliberation note.
     */
    public function addNote(Request $request, int $caseId): JsonResponse
    {
        $case = TribunalCase::findOrFail($caseId);
        $user = auth()->user();

        $data = $request->validate([
            'body' => 'required|string|max:10000',
            'note_type' => 'nullable|string|in:general,evidence_analysis,witness_analysis,credibility,issue_analysis,remedy_consideration',
        ]);

        $note = $this->deliberationService->addNote($case, $user->id, $data);

        return response()->json([
            'message' => 'Deliberation note recorded.',
            'note' => $note->load('author.profile'),
            'data' => $note->load('author.profile'),
        ], 201);
    }

    /**
     * Update a private deliberation note.
     */
    public function updateNote(Request $request, int $caseId, int $noteId): JsonResponse
    {
        $case = TribunalCase::findOrFail($caseId);
        $note = TribunalDeliberationNote::whereHas('deliberation', function ($q) use ($caseId) {
            $q->where('tribunal_case_id', $caseId);
        })->findOrFail($noteId);

        $user = auth()->user();

        $data = $request->validate([
            'body' => 'required|string|max:10000',
            'note_type' => 'nullable|string|in:general,evidence_analysis,witness_analysis,credibility,issue_analysis,remedy_consideration',
        ]);

        $updated = $this->deliberationService->updateNote($note, $user->id, $data);

        return response()->json([
            'message' => 'Deliberation note updated.',
            'note' => $updated,
            'data' => $updated,
        ]);
    }

    /**
     * Delete a private deliberation note.
     */
    public function deleteNote(int $caseId, int $noteId): JsonResponse
    {
        $note = TribunalDeliberationNote::whereHas('deliberation', function ($q) use ($caseId) {
            $q->where('tribunal_case_id', $caseId);
        })->findOrFail($noteId);

        $user = auth()->user();

        $this->deliberationService->deleteNote($note, $user->id);

        return response()->json([
            'message' => 'Deliberation note deleted.',
        ]);
    }

    /**
     * Add a finding of fact or issue determination.
     */
    public function addFinding(Request $request, int $caseId): JsonResponse
    {
        $case = TribunalCase::findOrFail($caseId);
        $user = auth()->user();

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'finding_text' => 'required|string|max:10000',
            'finding_type' => 'nullable|string|in:fact,issue,credibility,evidence,procedural',
            'conclusion' => 'nullable|string|in:established,not_established,partially_established,not_applicable',
            'display_order' => 'nullable|integer|min:1',
            'is_public' => 'nullable|boolean',
            'evidence_ids' => 'nullable|array',
            'evidence_ids.*' => 'integer|exists:tribunal_evidence,id',
            'hearing_entry_ids' => 'nullable|array',
            'hearing_entry_ids.*' => 'integer|exists:tribunal_hearing_entries,id',
            'witness_ids' => 'nullable|array',
            'witness_ids.*' => 'integer|exists:tribunal_witnesses,id',
        ]);

        $finding = $this->deliberationService->addFinding($case, $user->id, $data);

        return response()->json([
            'message' => 'Finding recorded.',
            'finding' => $finding,
            'data' => $finding,
        ], 201);
    }

    /**
     * Update an existing finding.
     */
    public function updateFinding(Request $request, int $caseId, int $findingId): JsonResponse
    {
        $finding = TribunalFinding::where('tribunal_case_id', $caseId)->findOrFail($findingId);
        $user = auth()->user();

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'finding_text' => 'sometimes|required|string|max:10000',
            'finding_type' => 'nullable|string|in:fact,issue,credibility,evidence,procedural',
            'conclusion' => 'nullable|string|in:established,not_established,partially_established,not_applicable',
            'display_order' => 'nullable|integer|min:1',
            'is_public' => 'nullable|boolean',
            'evidence_ids' => 'nullable|array',
            'evidence_ids.*' => 'integer|exists:tribunal_evidence,id',
            'hearing_entry_ids' => 'nullable|array',
            'hearing_entry_ids.*' => 'integer|exists:tribunal_hearing_entries,id',
            'witness_ids' => 'nullable|array',
            'witness_ids.*' => 'integer|exists:tribunal_witnesses,id',
        ]);

        $updated = $this->deliberationService->updateFinding($finding, $user->id, $data);

        return response()->json([
            'message' => 'Finding updated.',
            'finding' => $updated,
            'data' => $updated,
        ]);
    }

    /**
     * Delete a finding before publication.
     */
    public function deleteFinding(int $caseId, int $findingId): JsonResponse
    {
        $finding = TribunalFinding::where('tribunal_case_id', $caseId)->findOrFail($findingId);
        $user = auth()->user();

        $this->deliberationService->deleteFinding($finding, $user->id);

        return response()->json([
            'message' => 'Finding deleted.',
        ]);
    }

    /**
     * Get draft decision and orders.
     */
    public function getDecision(int $caseId): JsonResponse
    {
        $case = TribunalCase::findOrFail($caseId);
        $user = auth()->user();

        $decision = $this->decisionService->getOrCreateDraftDecision($case, $user->id);

        return response()->json([
            'decision' => $decision->load(['juryPanel', 'orders']),
            'data' => $decision->load(['juryPanel', 'orders']),
        ]);
    }

    /**
     * Update draft decision parameters.
     */
    public function updateDecision(Request $request, int $caseId): JsonResponse
    {
        $case = TribunalCase::findOrFail($caseId);
        $user = auth()->user();

        $data = $request->validate([
            'outcome' => 'nullable|string|in:complaint_upheld,complaint_partially_upheld,complaint_not_upheld,dismissed',
            'summary' => 'nullable|string|max:5000',
            'reasoning' => 'nullable|string|max:20000',
        ]);

        $decision = $this->decisionService->updateDraftDecision($case, $user->id, $data);

        return response()->json([
            'message' => 'Decision draft updated.',
            'decision' => $decision,
            'data' => $decision,
        ]);
    }

    /**
     * Add a remedy order to decision draft.
     */
    public function addOrder(Request $request, int $caseId): JsonResponse
    {
        $case = TribunalCase::findOrFail($caseId);
        $user = auth()->user();

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'order_type' => 'nullable|string|in:no_action,warning,corrective_action,content_action,account_action,compensation_recommendation,compliance_requirement,other',
            'target_side' => 'nullable|string|in:complainant,respondent,both',
            'deadline_at' => 'nullable|date',
        ]);

        $order = $this->decisionService->addOrder($case, $user->id, $data);

        return response()->json([
            'message' => 'Decision order added.',
            'order' => $order,
            'data' => $order,
        ], 201);
    }

    /**
     * Update an order.
     */
    public function updateOrder(Request $request, int $caseId, int $orderId): JsonResponse
    {
        $order = TribunalDecisionOrder::whereHas('decision', function ($q) use ($caseId) {
            $q->where('tribunal_case_id', $caseId);
        })->findOrFail($orderId);

        $user = auth()->user();

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:5000',
            'order_type' => 'nullable|string|in:no_action,warning,corrective_action,content_action,account_action,compensation_recommendation,compliance_requirement,other',
            'target_side' => 'nullable|string|in:complainant,respondent,both',
            'deadline_at' => 'nullable|date',
        ]);

        $updated = $this->decisionService->updateOrder($order, $user->id, $data);

        return response()->json([
            'message' => 'Order updated.',
            'order' => $updated,
            'data' => $updated,
        ]);
    }

    /**
     * Delete an order.
     */
    public function deleteOrder(int $caseId, int $orderId): JsonResponse
    {
        $order = TribunalDecisionOrder::whereHas('decision', function ($q) use ($caseId) {
            $q->where('tribunal_case_id', $caseId);
        })->findOrFail($orderId);

        $user = auth()->user();

        $this->decisionService->deleteOrder($order, $user->id);

        return response()->json([
            'message' => 'Order deleted.',
        ]);
    }

    /**
     * Publish the final Tribunal decision.
     */
    public function publish(int $caseId): JsonResponse
    {
        $case = TribunalCase::findOrFail($caseId);
        $user = auth()->user();

        $decision = $this->decisionService->publishDecision($case, $user->id);

        return response()->json([
            'message' => 'Final Tribunal decision published successfully. Case moved to appeal window.',
            'decision' => $decision,
            'data' => $decision,
        ]);
    }
}
