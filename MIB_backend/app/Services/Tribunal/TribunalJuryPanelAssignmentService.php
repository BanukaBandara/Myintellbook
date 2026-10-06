<?php

namespace App\Services\Tribunal;

use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalJuryPanelAssignmentMethod;
use App\Enums\TribunalJuryPanelAssignmentStatus;
use App\Enums\TribunalJuryPanelStatus;
use App\Enums\TribunalPartyRole;
use App\Models\TribunalCase;
use App\Models\TribunalJuryPanel;
use App\Models\TribunalJuryPanelAssignment;
use App\Models\TribunalJuryPanelEvent;
use App\Notifications\Tribunal\TribunalJuryPanelAssignedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TribunalJuryPanelAssignmentService
{
    /**
     * Find all active Jury Panels.
     *
     * @return Collection<int, TribunalJuryPanel>
     */
    public function findActivePanels(): Collection
    {
        return TribunalJuryPanel::where('status', TribunalJuryPanelStatus::Active)->get();
    }

    /**
     * Select the best eligible Jury Panel using lowest-workload distribution.
     */
    public function selectPanelForCase(TribunalCase $tribunalCase): ?TribunalJuryPanel
    {
        $panels = TribunalJuryPanel::where('status', TribunalJuryPanelStatus::Active)
            ->withCount(['activeAssignments'])
            ->withMax('panelAssignments as last_assigned_at', 'assigned_at')
            ->get();

        if ($panels->isEmpty()) {
            return null;
        }

        $sorted = $panels->sort(function ($a, $b) {
            // 1. Lowest active assignments count
            if ($a->active_assignments_count !== $b->active_assignments_count) {
                return $a->active_assignments_count <=> $b->active_assignments_count;
            }

            // 2. Oldest last-assigned timestamp (panels never assigned come first: null < timestamp)
            if ($a->last_assigned_at === null && $b->last_assigned_at !== null) {
                return -1;
            }
            if ($a->last_assigned_at !== null && $b->last_assigned_at === null) {
                return 1;
            }
            if ($a->last_assigned_at !== $b->last_assigned_at) {
                return strcmp((string) $a->last_assigned_at, (string) $b->last_assigned_at);
            }

            // 3. Deterministic fallback by id
            return $a->id <=> $b->id;
        })->values();

        return $sorted->first();
    }

    /**
     * Assign a Tribunal case to an active Jury Panel transactionally.
     */
    public function assignCase(
        TribunalCase $tribunalCase,
        string $method = 'automatic',
        ?int $assignedBy = null
    ): ?TribunalJuryPanelAssignment {
        return DB::transaction(function () use ($tribunalCase, $method, $assignedBy) {
            // Lock case row to prevent race conditions
            $lockedCase = TribunalCase::where('id', $tribunalCase->id)->lockForUpdate()->first();
            if (!$lockedCase) {
                return null;
            }

            // Idempotency: check if an active assignment already exists
            $existingAssignment = TribunalJuryPanelAssignment::where('tribunal_case_id', $lockedCase->id)
                ->where('status', TribunalJuryPanelAssignmentStatus::Active)
                ->first();

            if ($existingAssignment) {
                return $existingAssignment;
            }

            // Log start of assignment process
            TribunalCaseEventService::log($lockedCase, 'jury_panel_assignment_started', $assignedBy, [
                'assignment_method' => $method,
            ]);

            // Select active panel with fair workload distribution
            $selectedPanel = $this->selectPanelForCase($lockedCase);

            if (!$selectedPanel) {
                // No active panel available: leave case in jury_selection awaiting panel
                $lockedCase->update([
                    'status' => TribunalCaseStatus::JurySelection,
                ]);

                TribunalCaseEventService::log($lockedCase, 'jury_panel_assignment_unavailable', $assignedBy, [
                    'reason' => 'No active jury panels available',
                    'assignment_method' => $method,
                ]);

                return null;
            }

            // Create assignment record
            $methodEnum = $method === 'manual_reassignment'
                ? TribunalJuryPanelAssignmentMethod::ManualReassignment
                : TribunalJuryPanelAssignmentMethod::Automatic;

            $assignment = TribunalJuryPanelAssignment::create([
                'tribunal_case_id' => $lockedCase->id,
                'tribunal_jury_panel_id' => $selectedPanel->id,
                'assigned_by' => $assignedBy,
                'assignment_method' => $methodEnum,
                'status' => TribunalJuryPanelAssignmentStatus::Active,
                'assigned_at' => now(),
            ]);

            // Transition case to evidence collection
            $lockedCase->update([
                'status' => TribunalCaseStatus::EvidenceCollection,
            ]);

            // Record case event
            TribunalCaseEventService::log($lockedCase, 'jury_panel_assigned', $assignedBy, [
                'jury_panel_id' => $selectedPanel->id,
                'panel_code' => $selectedPanel->panel_code,
                'panel_name' => $selectedPanel->panel_name,
                'assignment_id' => $assignment->id,
                'assignment_method' => $method,
            ]);

            // Record safe event on the Jury Panel
            TribunalJuryPanelEvent::create([
                'tribunal_jury_panel_id' => $selectedPanel->id,
                'actor_id' => $assignedBy,
                'event_type' => 'case_assigned',
                'metadata' => [
                    'tribunal_case_id' => $lockedCase->id,
                    'case_number' => $lockedCase->case_number,
                    'assignment_id' => $assignment->id,
                    'assignment_method' => $method,
                ],
                'created_at' => now(),
            ]);

            // Notify Jury Panel user account
            $panelUser = $selectedPanel->loginUser;
            $panelUser?->notify(new TribunalJuryPanelAssignedNotification($lockedCase, $assignment));

            return $assignment->load(['tribunalCase', 'juryPanel']);
        });
    }

    /**
     * Get the active assignment for a case.
     */
    public function getAssignmentForCase(int $caseId): ?TribunalJuryPanelAssignment
    {
        return TribunalJuryPanelAssignment::where('tribunal_case_id', $caseId)
            ->where('status', TribunalJuryPanelAssignmentStatus::Active)
            ->with('juryPanel')
            ->first();
    }

    /**
     * Get paginated assigned cases for a Jury Panel.
     */
    public function getAssignedCasesForPanel(
        int $panelId,
        ?string $status = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = TribunalJuryPanelAssignment::where('tribunal_jury_panel_id', $panelId)
            ->with([
                'tribunalCase.parties.user.profile',
            ])
            ->latest('assigned_at');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Release an assignment.
     */
    public function releaseAssignment(
        TribunalJuryPanelAssignment $assignment,
        string $reason,
        ?int $actorId = null
    ): TribunalJuryPanelAssignment {
        return DB::transaction(function () use ($assignment, $reason, $actorId) {
            $assignment->update([
                'status' => TribunalJuryPanelAssignmentStatus::Released,
                'released_at' => now(),
                'release_reason' => $reason,
            ]);

            TribunalCaseEventService::log($assignment->tribunalCase, 'jury_panel_assignment_released', $actorId, [
                'jury_panel_id' => $assignment->tribunal_jury_panel_id,
                'assignment_id' => $assignment->id,
                'reason' => $reason,
            ]);

            return $assignment;
        });
    }
}
