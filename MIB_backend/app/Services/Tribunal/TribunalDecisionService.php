<?php

namespace App\Services\Tribunal;

use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalDecisionOrderStatus;
use App\Enums\TribunalDecisionOrderType;
use App\Enums\TribunalDecisionOutcome;
use App\Enums\TribunalDecisionStatus;
use App\Enums\TribunalDeliberationStatus;
use App\Enums\TribunalHearingStatus;
use App\Models\TribunalCase;
use App\Models\TribunalDecision;
use App\Models\TribunalDecisionOrder;
use App\Models\User;
use App\Notifications\Tribunal\TribunalDecisionPublishedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TribunalDecisionService
{
    /**
     * Resolve user ID from int, User, or TribunalJuryPanel.
     */
    public function resolveUserId(mixed $userOrId): int
    {
        if ($userOrId instanceof \App\Models\TribunalJuryPanel) {
            return (int) $userOrId->login_user_id;
        }
        if ($userOrId instanceof \App\Models\User) {
            return (int) $userOrId->id;
        }
        return (int) $userOrId;
    }

    /**
     * Ensure the user is the assigned active Jury Panel account for the case.
     */
    public function ensureAssignedJuryPanel(TribunalCase $case, mixed $userOrId): void
    {
        $userId = $this->resolveUserId($userOrId);
        if (!$case->isAssignedJuryPanelUser($userId)) {
            abort(403, 'Unauthorized. Only the assigned active Jury Panel can formulate or publish decisions.');
        }
    }

    /**
     * Get or lazy-create the case's draft decision record.
     */
    public function getOrCreateDraftDecision(TribunalCase $case, mixed $userOrId): TribunalDecision
    {
        $userId = $this->resolveUserId($userOrId);
        $this->ensureAssignedJuryPanel($case, $userId);

        $assignment = $case->currentJuryPanelAssignment;
        $panel = $assignment ? $assignment->juryPanel : null;
        if (!$panel) {
            abort(422, 'No active Jury Panel assigned to this case.');
        }

        $nextSeq = (TribunalDecision::max('id') ?? 0) + 1;
        $decisionNumber = 'DEC-' . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

        return TribunalDecision::firstOrCreate(
            ['tribunal_case_id' => $case->id],
            [
                'tribunal_jury_panel_id' => $panel->id,
                'decision_number' => $decisionNumber,
                'status' => TribunalDecisionStatus::Draft,
                'created_by' => $userId,
            ]
        )->load(['juryPanel', 'orders']);
    }

    /**
     * Update draft decision details (outcome, summary, reasoning).
     */
    public function updateDraftDecision(TribunalCase $case, mixed $userOrId, array $data): TribunalDecision
    {
        $userId = $this->resolveUserId($userOrId);
        $this->ensureAssignedJuryPanel($case, $userId);
        $decision = $this->getOrCreateDraftDecision($case, $userId);

        if ($decision->isFinal()) {
            abort(409, 'Final decision has already been published and cannot be modified.');
        }

        $outcome = isset($data['outcome'])
            ? (is_string($data['outcome']) ? TribunalDecisionOutcome::from($data['outcome']) : $data['outcome'])
            : $decision->outcome;

        $decision->update([
            'outcome' => $outcome,
            'summary' => array_key_exists('summary', $data) ? $data['summary'] : $decision->summary,
            'reasoning' => array_key_exists('reasoning', $data) ? $data['reasoning'] : $decision->reasoning,
        ]);

        return $decision->load(['juryPanel', 'orders']);
    }

    /**
     * Save draft decision alias supporting various caller signatures.
     */
    public function saveDraft(TribunalCase $case, mixed $p1, mixed $p2 = null, array $p3 = []): TribunalDecision
    {
        if (is_array($p2)) {
            $userId = $this->resolveUserId($p1);
            $data = $p2;
        } else {
            $userId = $this->resolveUserId($p2 ?? $p1);
            $data = $p3;
        }

        return $this->updateDraftDecision($case, $userId, $data);
    }

    /**
     * Add a remedy or order to the decision draft.
     */
    public function addOrder(TribunalCase|TribunalDecision $caseOrDecision, mixed $p1, array $p2 = []): TribunalDecisionOrder
    {
        if ($caseOrDecision instanceof TribunalDecision) {
            $decision = $caseOrDecision;
            $case = $decision->case;
            $userId = $decision->created_by;
            $data = is_array($p1) ? $p1 : $p2;
        } else {
            $case = $caseOrDecision;
            $userId = $this->resolveUserId($p1);
            $data = $p2;
            $decision = $this->getOrCreateDraftDecision($case, $userId);
        }

        $this->ensureAssignedJuryPanel($case, $userId);

        if ($decision->isFinal()) {
            abort(409, 'Cannot add orders to an already published final decision.');
        }

        $orderType = isset($data['order_type'])
            ? (is_string($data['order_type']) ? TribunalDecisionOrderType::from($data['order_type']) : $data['order_type'])
            : TribunalDecisionOrderType::Warning;

        $nextSeq = (TribunalDecisionOrder::where('tribunal_decision_id', $decision->id)->count()) + 1;
        $orderNumber = 'ORD-' . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

        $order = TribunalDecisionOrder::create([
            'tribunal_decision_id' => $decision->id,
            'order_number' => $orderNumber,
            'order_type' => $orderType,
            'title' => $data['title'],
            'description' => $data['description'],
            'target_side' => $data['target_side'] ?? null,
            'deadline_at' => $data['deadline_at'] ?? null,
            'status' => TribunalDecisionOrderStatus::Recorded,
        ]);

        TribunalCaseEventService::log($case, 'decision_order_added', $userId, [
            'decision_id' => $decision->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);

        return $order;
    }

    /**
     * Update an order before publication.
     */
    public function updateOrder(TribunalDecisionOrder $order, int $userId, array $data): TribunalDecisionOrder
    {
        $decision = $order->decision;
        $case = $decision->case;
        $this->ensureAssignedJuryPanel($case, $userId);

        if ($decision->isFinal()) {
            abort(409, 'Cannot modify orders after final decision publication.');
        }

        $order->update([
            'title' => $data['title'] ?? $order->title,
            'description' => $data['description'] ?? $order->description,
            'target_side' => array_key_exists('target_side', $data) ? $data['target_side'] : $order->target_side,
            'deadline_at' => array_key_exists('deadline_at', $data) ? $data['deadline_at'] : $order->deadline_at,
            'order_type' => isset($data['order_type'])
                ? (is_string($data['order_type']) ? TribunalDecisionOrderType::from($data['order_type']) : $data['order_type'])
                : $order->order_type,
        ]);

        TribunalCaseEventService::log($case, 'decision_order_updated', $userId, [
            'decision_id' => $decision->id,
            'order_id' => $order->id,
        ]);

        return $order;
    }

    /**
     * Delete an order before publication.
     */
    public function deleteOrder(TribunalDecisionOrder $order, int $userId): void
    {
        $decision = $order->decision;
        $case = $decision->case;
        $this->ensureAssignedJuryPanel($case, $userId);

        if ($decision->isFinal()) {
            abort(409, 'Cannot delete orders after final decision publication.');
        }

        $orderId = $order->id;
        $orderNumber = $order->order_number;
        $order->delete();

        TribunalCaseEventService::log($case, 'decision_order_removed', $userId, [
            'decision_id' => $decision->id,
            'order_id' => $orderId,
            'order_number' => $orderNumber,
        ]);
    }

    /**
     * Publish the final Tribunal decision.
     * Transitions case to 'appeal_window' and locks decision, findings, and notes.
     */
    public function publishDecision(TribunalCase $case, int $userId): TribunalDecision
    {
        // 1. Authorize: Only assigned active Jury Panel
        $this->ensureAssignedJuryPanel($case, $userId);

        // Prevent double publication
        if ($case->finalDecision()->exists() || $case->status === TribunalCaseStatus::AppealWindow || $case->status === TribunalCaseStatus::Decided) {
            abort(409, 'A final decision has already been finalized and published.');
        }

        // 2. Validate Case Status
        if ($case->status !== TribunalCaseStatus::Deliberation) {
            abort(422, 'Case must be in deliberation stage to publish a final decision.');
        }

        // 3. Validate Completed Formal Hearing
        $hasCompletedHearing = $case->hearings()
            ->where('status', TribunalHearingStatus::Completed)
            ->exists();
        if (!$hasCompletedHearing) {
            abort(422, 'Cannot publish decision without a completed formal hearing.');
        }

        // 4. Validate Decision Draft
        $decision = $case->decision;
        if (!$decision) {
            abort(422, 'No decision draft exists for this case.');
        }

        if ($decision->isFinal()) {
            abort(409, 'Decision has already been finalized and published.');
        }

        // 5. Validate Required Fields
        if (!$decision->outcome) {
            abort(422, 'Decision outcome must be determined before publication.');
        }
        if (empty(trim((string) $decision->summary))) {
            abort(422, 'Decision summary is required before publication.');
        }
        if (empty(trim((string) $decision->reasoning))) {
            abort(422, 'Tribunal reasoning is required before publication.');
        }

        // 6. Validate at least one public finding exists
        $hasPublicFinding = $case->findings()
            ->where('is_public', true)
            ->exists();
        if (!$hasPublicFinding) {
            abort(422, 'At least one public finding of fact or issue determination is required.');
        }

        // 7. Atomic Publication Transaction
        return DB::transaction(function () use ($case, $decision, $userId) {
            $windowDays = (int) config('tribunal.appeal_window_days', 14);
            $publishedAt = now();
            $appealDeadline = $publishedAt->copy()->addDays($windowDays);

            // Mark Decision Final
            $decision->update([
                'status' => TribunalDecisionStatus::Final,
                'published_at' => $publishedAt,
                'appeal_deadline' => $appealDeadline,
            ]);

            // Complete Deliberation
            $deliberation = $case->deliberation;
            if ($deliberation) {
                $deliberation->update([
                    'status' => TribunalDeliberationStatus::Completed,
                    'completed_at' => $publishedAt,
                ]);
            }

            // Move Case to appeal_window
            $case->update([
                'status' => TribunalCaseStatus::AppealWindow,
            ]);

            // Log Audit Events
            TribunalCaseEventService::log($case, 'decision_published', $userId, [
                'decision_id' => $decision->id,
                'decision_number' => $decision->decision_number,
                'outcome' => $decision->outcome->value,
                'published_at' => $publishedAt->toIso8601String(),
            ]);

            TribunalCaseEventService::log($case, 'appeal_window_opened', $userId, [
                'appeal_deadline' => $appealDeadline->toIso8601String(),
                'window_days' => $windowDays,
            ]);

            // Notify Case Participants (Complainant, Respondent, and Active Lawyers)
            $recipients = $this->getDecisionNotificationRecipients($case);
            if (!empty($recipients)) {
                Notification::send($recipients, new TribunalDecisionPublishedNotification($decision));
            }

            return $decision->load(['juryPanel', 'orders']);
        });
    }

    /**
     * Get recipients to notify upon decision publication.
     *
     * @return User[]
     */
    protected function getDecisionNotificationRecipients(TribunalCase $case): array
    {
        $userIds = [];

        foreach ($case->parties as $party) {
            $userIds[] = $party->user_id;
        }

        foreach ($case->activeRepresentativeAssignments as $rep) {
            $userIds[] = $rep->representative_user_id;
        }

        $userIds = array_values(array_unique(array_filter($userIds)));

        return User::whereIn('id', $userIds)->get()->all();
    }
}
