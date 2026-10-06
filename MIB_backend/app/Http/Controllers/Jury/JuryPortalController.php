<?php

namespace App\Http\Controllers\Jury;

use App\Enums\TribunalJuryPanelAssignmentStatus;
use App\Enums\TribunalPartyRole;
use App\Http\Controllers\Controller;
use App\Models\TribunalCase;
use App\Models\TribunalJuryPanelAssignment;
use App\Services\Tribunal\TribunalJuryPanelAssignmentService;
use App\Http\Requests\Tribunal\AskAdjudicatorQuestionRequest;
use App\Http\Requests\Tribunal\EndMediationRequest;
use App\Http\Requests\Tribunal\PostProceduralNoticeRequest;
use App\Http\Requests\Tribunal\SendCaseRoomMessageRequest;
use App\Http\Resources\Tribunal\TribunalCaseMessageResource;
use App\Http\Resources\Tribunal\TribunalMediationResource;
use App\Services\Tribunal\TribunalCaseRoomService;
use App\Services\Tribunal\TribunalMediationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JuryPortalController extends Controller
{
    public function __construct(
        protected TribunalJuryPanelAssignmentService $assignmentService,
        protected TribunalCaseRoomService $caseRoomService,
        protected TribunalMediationService $mediationService
    ) {}

    /**
     * Get safe Jury Panel account information.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user() ?: auth()->user();
        $panel = $user->juryPanel;

        return response()->json([
            'status' => true,
            'is_jury_panel' => true,
            'panel' => [
                'id' => $panel->id,
                'panel_code' => $panel->panel_code,
                'panel_name' => $panel->panel_name,
                'status' => $panel->status instanceof \BackedEnum ? $panel->status->value : (string) $panel->status,
                'assigned_cases_count' => $panel->panelAssignments()->count(),
                'active_cases_count' => $panel->activeAssignments()->count(),
            ],
        ]);
    }

    /**
     * List assigned cases for this Jury Panel.
     */
    public function cases(Request $request): JsonResponse
    {
        $user = $request->user() ?: auth()->user();
        $panel = $user->juryPanel;

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $this->assignmentService->getAssignedCasesForPanel(
            $panel->id,
            TribunalJuryPanelAssignmentStatus::Active->value,
            $perPage
        );

        $items = collect($paginator->items())->map(function (TribunalJuryPanelAssignment $assignment) {
            $case = $assignment->tribunalCase;
            if (!$case) {
                return null;
            }

            $complainant = $case->parties->firstWhere('role', TribunalPartyRole::Complainant);
            $respondent = $case->parties->firstWhere('role', TribunalPartyRole::Respondent);

            $complainantName = $complainant?->user?->profile?->full_name 
                ?: trim(($complainant?->user?->profile?->first_name ?? '') . ' ' . ($complainant?->user?->profile?->last_name ?? ''));
            $respondentName = $respondent?->user?->profile?->full_name 
                ?: trim(($respondent?->user?->profile?->first_name ?? '') . ' ' . ($respondent?->user?->profile?->last_name ?? ''));

            return [
                'id' => $case->id,
                'case_number' => $case->case_number,
                'title' => $case->title,
                'category' => $case->category,
                'status' => $case->status instanceof \BackedEnum ? $case->status->value : $case->status,
                'severity' => $case->severity,
                'complainant_summary' => [
                    'id' => $complainant?->id,
                    'user_id' => $complainant?->user_id,
                    'name' => $complainantName ?: ('User #' . ($complainant?->user_id ?? '')),
                ],
                'respondent_summary' => [
                    'id' => $respondent?->id,
                    'user_id' => $respondent?->user_id,
                    'name' => $respondentName ?: ('User #' . ($respondent?->user_id ?? '')),
                ],
                'assigned_at' => $assignment->assigned_at?->toISOString(),
                'assignment_status' => $assignment->status instanceof \BackedEnum ? $assignment->status->value : $assignment->status,
                'updated_at' => $case->updated_at?->toISOString(),
            ];
        })->filter()->values();

        return response()->json([
            'status' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Show assigned case details.
     */
    public function showCase(Request $request, $id): JsonResponse
    {
        $user = $request->user() ?: auth()->user();
        $panel = $user->juryPanel;

        // Strict authorization: active assignment belonging to this panel
        $assignment = TribunalJuryPanelAssignment::where('tribunal_case_id', $id)
            ->where('tribunal_jury_panel_id', $panel->id)
            ->where('status', TribunalJuryPanelAssignmentStatus::Active)
            ->first();

        if (!$assignment) {
            return response()->json([
                'status' => false,
                'message' => 'Case not found or not assigned to this Jury Panel.',
            ], 404);
        }

        $tribunalCase = TribunalCase::where('id', $id)
            ->with([
                'parties.user.profile',
                'response',
                'evidence.uploader.profile',
                'evidence.challenges.challenger.profile',
                'activeRepresentativeAssignments.representative.profile',
                'activeRepresentativeAssignments.representative.latestProfessionalVerification',
                'activeRepresentativeAssignments.client.profile',
                'activeMediation',
                'events.actor.profile',
            ])
            ->firstOrFail();

        // Parties
        $complainantParty = $tribunalCase->parties->firstWhere('role', TribunalPartyRole::Complainant);
        $respondentParty = $tribunalCase->parties->firstWhere('role', TribunalPartyRole::Respondent);

        $complainantName = $complainantParty?->user?->profile?->full_name
            ?: trim(($complainantParty?->user?->profile?->first_name ?? '') . ' ' . ($complainantParty?->user?->profile?->last_name ?? ''));
        $respondentName = $respondentParty?->user?->profile?->full_name
            ?: trim(($respondentParty?->user?->profile?->first_name ?? '') . ' ' . ($respondentParty?->user?->profile?->last_name ?? ''));

        // Representation
        $complainantRep = $complainantParty
            ? $tribunalCase->activeRepresentativeFor($complainantParty->user_id)
            : null;
        $respondentRep = $respondentParty
            ? $tribunalCase->activeRepresentativeFor($respondentParty->user_id)
            : null;

        $formatRep = function ($repAssignment) {
            if (!$repAssignment) {
                return null;
            }
            $repUser = $repAssignment->representative;
            $profile = $repUser?->profile;
            $verification = $repUser?->latestProfessionalVerification;
            $name = $profile?->full_name ?: trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));

            return [
                'id' => $repAssignment->id,
                'representative_user_id' => $repAssignment->representative_user_id,
                'representative_name' => $name ?: ('Attorney #' . $repAssignment->representative_user_id),
                'assigned_at' => $repAssignment->assigned_at?->toISOString(),
                'bar_number' => $verification?->bar_number,
                'jurisdiction' => $verification?->jurisdiction,
            ];
        };

        // Evidence list with metadata & download URLs
        $evidence = $tribunalCase->evidence->map(function ($ev) use ($tribunalCase) {
            $uploader = $ev->uploader?->profile;
            $uploaderName = $uploader?->full_name ?: trim(($uploader?->first_name ?? '') . ' ' . ($uploader?->last_name ?? ''));

            return [
                'id' => $ev->id,
                'evidence_number' => $ev->evidence_number,
                'title' => $ev->title,
                'description' => $ev->description,
                'category' => $ev->type instanceof \BackedEnum ? $ev->type->value : (string) $ev->type,
                'original_filename' => $ev->original_filename,
                'file_size' => $ev->file_size,
                'mime_type' => $ev->mime_type,
                'uploaded_by' => [
                    'id' => $ev->uploaded_by,
                    'name' => $uploaderName ?: ('User #' . $ev->uploaded_by),
                ],
                'status' => $ev->status instanceof \BackedEnum ? $ev->status->value : $ev->status,
                'download_url' => "/api/tribunal/cases/{$tribunalCase->id}/evidence/{$ev->id}/download",
                'created_at' => $ev->created_at?->toISOString(),
                'challenges' => $ev->challenges->map(function ($ch) {
                    $challenger = $ch->challenger?->profile;
                    $challengerName = $challenger?->full_name ?: trim(($challenger?->first_name ?? '') . ' ' . ($challenger?->last_name ?? ''));
                    return [
                        'id' => $ch->id,
                        'reason' => $ch->reason,
                        'status' => $ch->status instanceof \BackedEnum ? $ch->status->value : $ch->status,
                        'challenger_name' => $challengerName ?: ('User #' . $ch->challenger_id),
                        'created_at' => $ch->created_at?->toISOString(),
                    ];
                }),
            ];
        });

        // Safe case timeline / events
        $timeline = $tribunalCase->events->map(function ($ev) {
            $actor = $ev->actor?->profile;
            $actorName = $actor?->full_name ?: trim(($actor?->first_name ?? '') . ' ' . ($actor?->last_name ?? ''));

            return [
                'id' => $ev->id,
                'event_type' => $ev->event_type,
                'actor_name' => $actorName ?: ($ev->actor_id ? 'User #' . $ev->actor_id : 'System'),
                'metadata' => $ev->metadata,
                'created_at' => $ev->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $tribunalCase->id,
                'case_number' => $tribunalCase->case_number,
                'title' => $tribunalCase->title,
                'category' => $tribunalCase->category,
                'description' => $tribunalCase->description,
                'requested_resolution' => $tribunalCase->requested_resolution,
                'severity' => $tribunalCase->severity,
                'status' => $tribunalCase->status instanceof \BackedEnum ? $tribunalCase->status->value : $tribunalCase->status,
                'submitted_at' => $tribunalCase->submitted_at?->toISOString(),
                'assigned_panel' => [
                    'panel_id' => $panel->id,
                    'panel_code' => $panel->panel_code,
                    'panel_name' => $panel->panel_name,
                    'assigned_at' => $assignment->assigned_at?->toISOString(),
                    'assignment_method' => $assignment->assignment_method instanceof \BackedEnum ? $assignment->assignment_method->value : $assignment->assignment_method,
                ],
                'parties' => [
                    'complainant' => [
                        'id' => $complainantParty?->id,
                        'user_id' => $complainantParty?->user_id,
                        'name' => $complainantName ?: ('User #' . ($complainantParty?->user_id ?? '')),
                    ],
                    'respondent' => [
                        'id' => $respondentParty?->id,
                        'user_id' => $respondentParty?->user_id,
                        'name' => $respondentName ?: ('User #' . ($respondentParty?->user_id ?? '')),
                    ],
                ],
                'representation' => [
                    'complainant_lawyer' => $formatRep($complainantRep),
                    'respondent_lawyer' => $formatRep($respondentRep),
                ],
                'response' => $tribunalCase->response ? [
                    'id' => $tribunalCase->response->id,
                    'acknowledgement_at' => $tribunalCase->response->acknowledgement_at?->toISOString(),
                    'position' => $tribunalCase->response->position instanceof \BackedEnum ? $tribunalCase->response->position->value : $tribunalCase->response->position,
                    'response_text' => $tribunalCase->response->response_text,
                    'submitted_at' => $tribunalCase->response->submitted_at?->toISOString(),
                ] : null,
                'evidence' => $evidence,
                'timeline' => $timeline,
                'mediation_summary' => $tribunalCase->activeMediation ? [
                    'id' => $tribunalCase->activeMediation->id,
                    'status' => $tribunalCase->activeMediation->status instanceof \BackedEnum ? $tribunalCase->activeMediation->status->value : $tribunalCase->activeMediation->status,
                    'started_at' => $tribunalCase->activeMediation->started_at?->toISOString(),
                ] : null,
            ],
        ]);
    }

    /**
     * Authorize and retrieve assigned case for the authenticated active Jury Panel.
     */
    protected function getAuthorizedAssignedCase(int|string $caseId): TribunalCase
    {
        $user = auth()->user();
        $panel = $user?->juryPanel;

        if (!$panel || !$panel->isActive()) {
            abort(403, 'Jury Panel is not active.');
        }

        $case = TribunalCase::findOrFail($caseId);

        $assignment = TribunalJuryPanelAssignment::where('tribunal_case_id', $case->id)
            ->where('tribunal_jury_panel_id', $panel->id)
            ->where('status', TribunalJuryPanelAssignmentStatus::Active)
            ->first();

        if (!$assignment) {
            abort(404, 'Case not assigned to this Jury Panel.');
        }

        return $case;
    }

    /**
     * Get shared case room messages for the assigned Jury Panel.
     */
    public function caseRoomMessages(Request $request, $id): JsonResponse
    {
        $case = $this->getAuthorizedAssignedCase($id);
        $user = $request->user() ?: auth()->user();

        $messages = $this->caseRoomService->getMessages(
            $case,
            $user->id,
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
     * Send a normal shared message from the Jury Panel.
     */
    public function sendCaseRoomMessage(SendCaseRoomMessageRequest $request, $id): JsonResponse
    {
        $case = $this->getAuthorizedAssignedCase($id);
        $user = $request->user() ?: auth()->user();

        $message = $this->caseRoomService->sendMessage(
            $case,
            $user->id,
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
     * Post a procedural notice from the Jury Panel.
     */
    public function postProceduralNotice(PostProceduralNoticeRequest $request, $id): JsonResponse
    {
        $case = $this->getAuthorizedAssignedCase($id);
        $user = $request->user() ?: auth()->user();

        $notice = $this->caseRoomService->postProceduralNotice(
            $case,
            $user->id,
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
     * Ask a targeted question from the Jury Panel.
     */
    public function askQuestion(AskAdjudicatorQuestionRequest $request, $id): JsonResponse
    {
        $case = $this->getAuthorizedAssignedCase($id);
        $user = $request->user() ?: auth()->user();

        $question = $this->caseRoomService->askQuestion(
            $case,
            $user->id,
            $request->validated('body'),
            $request->validated('target_side')
        );

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Jury Panel question posted successfully.',
            'data' => new TribunalCaseMessageResource($question),
        ], 201);
    }

    /**
     * View mediation status and proposal history for assigned case.
     */
    public function mediationShow(Request $request, $id): JsonResponse
    {
        $case = $this->getAuthorizedAssignedCase($id);

        $mediation = $case->mediations()
            ->with([
                'consents.user.profile',
                'proposals.proposer.profile',
                'proposals.acceptances',
                'settlementAgreement',
            ])
            ->latest()
            ->first();

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => $mediation ? new TribunalMediationResource($mediation) : null,
        ]);
    }

    /**
     * Offer voluntary mediation from the Jury Panel.
     */
    public function mediationOffer(Request $request, $id): JsonResponse
    {
        $case = $this->getAuthorizedAssignedCase($id);
        $user = $request->user() ?: auth()->user();

        $mediation = $this->mediationService->offerMediation($case, $user->id);

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Mediation offered to both parties successfully.',
            'data' => new TribunalMediationResource($mediation),
        ], 201);
    }

    /**
     * Conclude / end mediation as failed from the Jury Panel.
     */
    public function mediationEnd(EndMediationRequest $request, $id): JsonResponse
    {
        $case = $this->getAuthorizedAssignedCase($id);
        $user = $request->user() ?: auth()->user();

        $mediation = $case->mediations()
            ->latest()
            ->firstOrFail();

        $ended = $this->mediationService->endMediation(
            $mediation,
            $user->id,
            $request->validated('reason')
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Mediation ended successfully.',
            'data' => new TribunalMediationResource($ended),
        ]);
    }
}
