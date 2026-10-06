<?php

namespace App\Services\Tribunal;

use App\Enums\TribunalCaseMessageType;
use App\Enums\TribunalCaseRoomStatus;
use App\Models\TribunalCase;
use App\Models\TribunalCaseMessage;
use App\Models\TribunalCaseRoom;
use App\Models\TribunalEvidence;
use App\Models\User;
use App\Notifications\Tribunal\TribunalAdjudicatorQuestionNotification;
use App\Notifications\Tribunal\TribunalCaseMessageNotification;
use App\Notifications\Tribunal\TribunalProceduralNoticeNotification;
use App\Notifications\Tribunal\TribunalQuestionResponseNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TribunalCaseRoomService
{
    /**
     * Ensure the given user is an authorized participant in the case room.
     * Returns the sender's case role.
     *
     * @throws HttpException
     */
    public function ensureAuthorized(TribunalCase $case, int $userId): string
    {
        $role = $case->getUserCaseRole($userId);

        if (!$role) {
            abort(403, 'Unauthorized. You are not an active participant, representative, or adjudicator for this case.');
        }

        return $role;
    }

    /**
     * Get or lazy-create the unique case room for the tribunal case.
     */
    public function getOrCreateRoom(TribunalCase $case, int $userId): TribunalCaseRoom
    {
        $this->ensureAuthorized($case, $userId);

        return TribunalCaseRoom::firstOrCreate(
            ['tribunal_case_id' => $case->id],
            [
                'status' => TribunalCaseRoomStatus::Active,
                'opened_at' => now(),
            ]
        );
    }

    /**
     * Fetch paginated messages for the case room.
     */
    public function getMessages(TribunalCase $case, int $userId, int $perPage = 50): LengthAwarePaginator
    {
        $this->ensureAuthorized($case, $userId);
        $room = $this->getOrCreateRoom($case, $userId);

        return TribunalCaseMessage::where('tribunal_case_room_id', $room->id)
            ->with([
                'sender.profile',
                'relatedEvidence',
                'responses.sender.profile',
            ])
            ->whereNull('parent_message_id') // Top-level messages (threaded responses are eager loaded)
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);
    }

    /**
     * Post a normal case message.
     */
    public function sendMessage(
        TribunalCase $case,
        int $userId,
        string $body,
        ?int $relatedEvidenceId = null
    ): TribunalCaseMessage {
        $senderRole = $this->ensureAuthorized($case, $userId);
        $room = $this->getOrCreateRoom($case, $userId);

        if ($relatedEvidenceId) {
            $evidenceExists = TribunalEvidence::where('tribunal_case_id', $case->id)
                ->where('id', $relatedEvidenceId)
                ->exists();

            if (!$evidenceExists) {
                abort(422, 'The referenced evidence does not belong to this case.');
            }
        }

        $message = TribunalCaseMessage::create([
            'tribunal_case_room_id' => $room->id,
            'tribunal_case_id' => $case->id,
            'sender_id' => $userId,
            'sender_case_role' => $senderRole,
            'message_type' => TribunalCaseMessageType::Message,
            'body' => $body,
            'related_evidence_id' => $relatedEvidenceId,
            'procedural' => false,
        ]);

        if ($senderRole === 'jury_panel') {
            $panel = User::find($userId)?->juryPanel;
            TribunalCaseEventService::log(
                $case,
                'jury_panel_case_room_message_posted',
                $userId,
                [
                    'jury_panel_id' => $panel?->id,
                    'panel_code' => $panel?->panel_code,
                    'message_id' => $message->id,
                    'sender_case_role' => $senderRole,
                    'related_evidence_id' => $relatedEvidenceId,
                ]
            );
        } else {
            TribunalCaseEventService::log(
                $case,
                'case_room_message_posted',
                $userId,
                [
                    'message_id' => $message->id,
                    'sender_case_role' => $senderRole,
                    'related_evidence_id' => $relatedEvidenceId,
                ]
            );
        }

        $sender = User::find($userId);
        $recipients = $this->getCaseParticipants($case, $userId);
        if (!empty($recipients)) {
            Notification::send($recipients, new TribunalCaseMessageNotification($case, $message, $sender));
        }

        return $message->load(['sender.profile', 'relatedEvidence']);
    }

    /**
     * Post a procedural notice (Jury Panel or Adjudicator only).
     */
    public function postProceduralNotice(TribunalCase $case, int $userId, string $body): TribunalCaseMessage
    {
        $senderRole = $this->ensureAuthorized($case, $userId);

        if ($senderRole !== 'jury_panel' && $senderRole !== 'adjudicator') {
            abort(403, 'Only the active Tribunal Jury Panel can post procedural notices.');
        }

        $room = $this->getOrCreateRoom($case, $userId);

        $message = TribunalCaseMessage::create([
            'tribunal_case_room_id' => $room->id,
            'tribunal_case_id' => $case->id,
            'sender_id' => $userId,
            'sender_case_role' => $senderRole,
            'message_type' => TribunalCaseMessageType::ProceduralNotice,
            'body' => $body,
            'procedural' => true,
        ]);

        if ($senderRole === 'jury_panel') {
            $panel = User::find($userId)?->juryPanel;
            TribunalCaseEventService::log(
                $case,
                'jury_panel_procedural_notice_posted',
                $userId,
                [
                    'jury_panel_id' => $panel?->id,
                    'panel_code' => $panel?->panel_code,
                    'message_id' => $message->id,
                    'procedural' => true,
                ]
            );
        } else {
            TribunalCaseEventService::log(
                $case,
                'procedural_notice_posted',
                $userId,
                [
                    'message_id' => $message->id,
                    'procedural' => true,
                ]
            );
        }

        $sender = User::find($userId);
        $recipients = $this->getCaseParticipants($case, $userId);
        if (!empty($recipients)) {
            Notification::send($recipients, new TribunalProceduralNoticeNotification($case, $message, $sender));
        }

        return $message->load(['sender.profile']);
    }

    /**
     * Post an adjudicator / jury panel question.
     */
    public function askQuestion(
        TribunalCase $case,
        int $userId,
        string $body,
        string $targetSide
    ): TribunalCaseMessage {
        $senderRole = $this->ensureAuthorized($case, $userId);

        if ($senderRole !== 'jury_panel' && $senderRole !== 'adjudicator') {
            abort(403, 'Only the active Tribunal Jury Panel can ask procedural questions.');
        }

        if (!in_array($targetSide, ['complainant', 'respondent', 'both'])) {
            abort(422, 'Target side must be complainant, respondent, or both.');
        }

        $room = $this->getOrCreateRoom($case, $userId);

        $message = TribunalCaseMessage::create([
            'tribunal_case_room_id' => $room->id,
            'tribunal_case_id' => $case->id,
            'sender_id' => $userId,
            'sender_case_role' => $senderRole,
            'message_type' => TribunalCaseMessageType::AdjudicatorQuestion,
            'body' => $body,
            'target_side' => $targetSide,
            'procedural' => true,
        ]);

        if ($senderRole === 'jury_panel') {
            $panel = User::find($userId)?->juryPanel;
            TribunalCaseEventService::log(
                $case,
                'jury_panel_question_posted',
                $userId,
                [
                    'jury_panel_id' => $panel?->id,
                    'panel_code' => $panel?->panel_code,
                    'message_id' => $message->id,
                    'target_side' => $targetSide,
                ]
            );
        } else {
            TribunalCaseEventService::log(
                $case,
                'adjudicator_question_posted',
                $userId,
                [
                    'message_id' => $message->id,
                    'target_side' => $targetSide,
                ]
            );
        }

        $sender = User::find($userId);
        $recipients = $this->getTargetedQuestionRecipients($case, $targetSide, $userId);
        if (!empty($recipients)) {
            Notification::send($recipients, new TribunalAdjudicatorQuestionNotification($case, $message, $sender));
        }

        return $message->load(['sender.profile']);
    }

    /**
     * Respond to an adjudicator question.
     */
    public function respondToQuestion(
        TribunalCase $case,
        TribunalCaseMessage $question,
        int $userId,
        string $body
    ): TribunalCaseMessage {
        $senderRole = $this->ensureAuthorized($case, $userId);
        $userSide = $case->getUserCaseSide($userId);

        if ($question->tribunal_case_id !== $case->id) {
            abort(422, 'The question does not belong to this case.');
        }

        if ($question->message_type !== TribunalCaseMessageType::AdjudicatorQuestion) {
            abort(422, 'The referenced message is not an adjudicator question.');
        }

        if ($senderRole === 'adjudicator' || $senderRole === 'jury_panel') {
            abort(422, 'The Tribunal Jury Panel cannot respond to their own question.');
        }

        // Verify target side matches user's side
        if ($question->target_side !== 'both' && $question->target_side !== $userSide) {
            abort(403, "This question is targeted to the {$question->target_side}. You cannot respond.");
        }

        $room = $this->getOrCreateRoom($case, $userId);

        $message = TribunalCaseMessage::create([
            'tribunal_case_room_id' => $room->id,
            'tribunal_case_id' => $case->id,
            'sender_id' => $userId,
            'sender_case_role' => $senderRole,
            'message_type' => TribunalCaseMessageType::QuestionResponse,
            'body' => $body,
            'target_side' => $userSide,
            'parent_message_id' => $question->id,
            'procedural' => true,
        ]);

        TribunalCaseEventService::log(
            $case,
            'question_response_posted',
            $userId,
            [
                'message_id' => $message->id,
                'question_id' => $question->id,
                'sender_case_role' => $senderRole,
            ]
        );

        $sender = User::find($userId);
        $recipients = $this->getCaseParticipants($case, $userId);
        if (!empty($recipients)) {
            Notification::send($recipients, new TribunalQuestionResponseNotification($case, $message, $sender));
        }

        return $message->load(['sender.profile']);
    }

    /**
     * Get targeted recipients for a question (complainant/respondent + active representatives).
     *
     * @return User[]
     */
    public function getTargetedQuestionRecipients(TribunalCase $case, string $targetSide, ?int $excludeUserId = null): array
    {
        $userIds = [];

        if ($targetSide === 'complainant' || $targetSide === 'both') {
            $compParty = $case->parties()->where('role', 'complainant')->first();
            if ($compParty) {
                $userIds[] = $compParty->user_id;
            }
            $compReps = $case->activeRepresentativeAssignments()
                ->where('side', 'complainant')
                ->pluck('representative_user_id')
                ->all();
            $userIds = array_merge($userIds, $compReps);
        }

        if ($targetSide === 'respondent' || $targetSide === 'both') {
            $respParty = $case->parties()->where('role', 'respondent')->first();
            if ($respParty) {
                $userIds[] = $respParty->user_id;
            }
            $respReps = $case->activeRepresentativeAssignments()
                ->where('side', 'respondent')
                ->pluck('representative_user_id')
                ->all();
            $userIds = array_merge($userIds, $respReps);
        }

        $userIds = array_values(array_unique(array_filter($userIds, fn ($id) => $id && $id !== $excludeUserId)));

        return User::whereIn('id', $userIds)->get()->all();
    }

    /**
     * Get all active case participants (complainant, respondent, active representatives, active jury panel / accepted adjudicator).
     *
     * @return User[]
     */
    public function getCaseParticipants(TribunalCase $case, ?int $excludeUserId = null): array
    {
        $userIds = [];

        // Complainant & Respondent
        foreach ($case->parties as $party) {
            $userIds[] = $party->user_id;
        }

        // Active representatives
        $reps = $case->activeRepresentativeAssignments;
        foreach ($reps as $rep) {
            $userIds[] = $rep->representative_user_id;
        }

        // Jury Panel (if assigned) OR legacy accepted adjudicator (only if no panel assigned)
        $panelAssignment = $case->currentJuryPanelAssignment;
        if ($panelAssignment && $panelAssignment->status === \App\Enums\TribunalJuryPanelAssignmentStatus::Active) {
            if ($panelAssignment->juryPanel?->login_user_id) {
                $userIds[] = $panelAssignment->juryPanel->login_user_id;
            }
        } else {
            $adj = $case->acceptedJuryAssignment;
            if ($adj) {
                $userIds[] = $adj->juror_id;
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds, fn ($id) => $id && $id !== $excludeUserId)));

        return User::whereIn('id', $userIds)->get()->all();
    }
}
