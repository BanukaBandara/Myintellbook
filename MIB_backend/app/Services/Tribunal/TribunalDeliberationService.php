<?php

namespace App\Services\Tribunal;

use App\Enums\TribunalDeliberationNoteType;
use App\Enums\TribunalDeliberationStatus;
use App\Enums\TribunalFindingConclusion;
use App\Enums\TribunalFindingType;
use App\Models\TribunalCase;
use App\Models\TribunalDeliberation;
use App\Models\TribunalDeliberationNote;
use App\Models\TribunalEvidence;
use App\Models\TribunalFinding;
use App\Models\TribunalHearingEntry;
use App\Models\TribunalWitness;
use Illuminate\Support\Facades\DB;

class TribunalDeliberationService
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
            abort(403, 'Unauthorized. Only the assigned active Jury Panel has access to deliberation.');
        }
    }

    /**
     * Get or lazy-create the case's private deliberation record.
     */
    public function getOrCreateDeliberation(TribunalCase $case, mixed $userOrId): TribunalDeliberation
    {
        $userId = $this->resolveUserId($userOrId);
        $this->ensureAssignedJuryPanel($case, $userId);

        $assignment = $case->currentJuryPanelAssignment;
        $panel = $assignment ? $assignment->juryPanel : null;
        if (!$panel) {
            abort(422, 'No active Jury Panel assigned to this case.');
        }

        $isNew = false;
        $deliberation = TribunalDeliberation::firstOrCreate(
            ['tribunal_case_id' => $case->id],
            [
                'tribunal_jury_panel_id' => $panel->id,
                'status' => TribunalDeliberationStatus::Open,
                'opened_at' => now(),
            ]
        );

        if ($deliberation->wasRecentlyCreated) {
            TribunalCaseEventService::log($case, 'deliberation_opened', $userId, [
                'deliberation_id' => $deliberation->id,
                'panel_id' => $panel->id,
            ]);
        }

        return $deliberation->load([
            'notes.author.profile',
            'findings.evidence',
            'findings.witnesses',
            'findings.hearingEntries',
        ]);
    }

    /**
     * Add a private deliberation working note (visible ONLY to Jury Panel).
     */
    public function addNote(TribunalCase|TribunalDeliberation $caseOrDelib, mixed $userOrId, array $data = []): TribunalDeliberationNote
    {
        if ($caseOrDelib instanceof TribunalDeliberation) {
            $case = $caseOrDelib->case;
            $deliberation = $caseOrDelib;
            $userId = $this->resolveUserId($userOrId);
        } else {
            $case = $caseOrDelib;
            $userId = $this->resolveUserId($userOrId);
            $deliberation = $this->getOrCreateDeliberation($case, $userId);
        }

        $this->ensureAssignedJuryPanel($case, $userId);

        if ($deliberation->isCompleted()) {
            abort(409, 'Deliberation has concluded and private notes are locked against editing.');
        }

        $noteType = isset($data['note_type'])
            ? (is_string($data['note_type']) ? TribunalDeliberationNoteType::from($data['note_type']) : $data['note_type'])
            : TribunalDeliberationNoteType::General;

        return TribunalDeliberationNote::create([
            'tribunal_deliberation_id' => $deliberation->id,
            'author_user_id' => $userId,
            'note_type' => $noteType,
            'body' => $data['body'],
        ]);
    }

    /**
     * Update a private deliberation note.
     */
    public function updateNote(TribunalDeliberationNote $note, mixed $userOrId, array $data): TribunalDeliberationNote
    {
        $userId = $this->resolveUserId($userOrId);
        $deliberation = $note->deliberation;
        $this->ensureAssignedJuryPanel($deliberation->case, $userId);

        if ($deliberation->isCompleted()) {
            abort(409, 'Deliberation has concluded and private notes are locked.');
        }

        $note->update([
            'body' => $data['body'] ?? $note->body,
            'note_type' => isset($data['note_type'])
                ? (is_string($data['note_type']) ? TribunalDeliberationNoteType::from($data['note_type']) : $data['note_type'])
                : $note->note_type,
        ]);

        return $note;
    }

    /**
     * Delete a private deliberation note.
     */
    public function deleteNote(TribunalDeliberationNote $note, mixed $userOrId): void
    {
        $userId = $this->resolveUserId($userOrId);
        $deliberation = $note->deliberation;
        $this->ensureAssignedJuryPanel($deliberation->case, $userId);

        if ($deliberation->isCompleted()) {
            abort(409, 'Deliberation has concluded and private notes are locked.');
        }

        $note->delete();
    }

    /**
     * Add a finding of fact or issue determination.
     */
    public function addFinding(TribunalCase $case, mixed $userOrId, array $data): TribunalFinding
    {
        $userId = $this->resolveUserId($userOrId);
        $this->ensureAssignedJuryPanel($case, $userId);

        if ($case->finalDecision()->exists()) {
            abort(409, 'Cannot add findings after a final Tribunal decision has been published.');
        }

        $deliberation = $this->getOrCreateDeliberation($case, $userId);

        // Validate references belong to the same case
        $this->validateReferences($case, $data);

        return DB::transaction(function () use ($case, $deliberation, $userId, $data) {
            $nextSeq = (TribunalFinding::where('tribunal_case_id', $case->id)->max('id') ?? 0) + 1;
            $findingNumber = 'FIND-' . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

            $findingType = isset($data['finding_type'])
                ? (is_string($data['finding_type']) ? TribunalFindingType::from($data['finding_type']) : $data['finding_type'])
                : TribunalFindingType::Fact;

            $conclusion = isset($data['conclusion'])
                ? (is_string($data['conclusion']) ? TribunalFindingConclusion::from($data['conclusion']) : $data['conclusion'])
                : TribunalFindingConclusion::Established;

            $displayOrder = $data['display_order'] ?? ((TribunalFinding::where('tribunal_case_id', $case->id)->max('display_order') ?? 0) + 1);

            $finding = TribunalFinding::create([
                'tribunal_case_id' => $case->id,
                'tribunal_deliberation_id' => $deliberation->id,
                'finding_number' => $findingNumber,
                'finding_type' => $findingType,
                'title' => $data['title'] ?? null,
                'finding_text' => $data['finding_text'],
                'conclusion' => $conclusion,
                'display_order' => $displayOrder,
                'is_public' => $data['is_public'] ?? true,
                'created_by' => $userId,
            ]);

            if (!empty($data['evidence_ids'])) {
                $finding->evidence()->sync($data['evidence_ids']);
            }

            if (!empty($data['hearing_entry_ids'])) {
                $finding->hearingEntries()->sync($data['hearing_entry_ids']);
            }

            if (!empty($data['witness_ids'])) {
                $finding->witnesses()->sync($data['witness_ids']);
            }

            TribunalCaseEventService::log($case, 'finding_added', $userId, [
                'finding_id' => $finding->id,
                'finding_number' => $finding->finding_number,
                'finding_type' => $finding->finding_type->value,
                'conclusion' => $finding->conclusion->value,
            ]);

            return $finding->load(['evidence', 'witnesses', 'hearingEntries']);
        });
    }

    /**
     * Alias for addFinding supporting varying test or caller signatures.
     */
    public function createFinding(TribunalCase $case, mixed $p1, mixed $p2 = null, array $p3 = []): TribunalFinding
    {
        if (is_array($p2)) {
            $userId = $this->resolveUserId($p1);
            $data = $p2;
        } else {
            $userId = $this->resolveUserId($p2 ?? $p1);
            $data = $p3;
        }

        return $this->addFinding($case, $userId, $data);
    }

    /**
     * Update an existing finding.
     */
    public function updateFinding(TribunalFinding $finding, int $userId, array $data): TribunalFinding
    {
        $case = $finding->case;
        $this->ensureAssignedJuryPanel($case, $userId);

        if ($case->finalDecision()->exists()) {
            abort(409, 'Cannot modify findings after a final Tribunal decision has been published.');
        }

        $this->validateReferences($case, $data);

        return DB::transaction(function () use ($finding, $case, $userId, $data) {
            $finding->update([
                'title' => array_key_exists('title', $data) ? $data['title'] : $finding->title,
                'finding_text' => $data['finding_text'] ?? $finding->finding_text,
                'finding_type' => isset($data['finding_type'])
                    ? (is_string($data['finding_type']) ? TribunalFindingType::from($data['finding_type']) : $data['finding_type'])
                    : $finding->finding_type,
                'conclusion' => isset($data['conclusion'])
                    ? (is_string($data['conclusion']) ? TribunalFindingConclusion::from($data['conclusion']) : $data['conclusion'])
                    : $finding->conclusion,
                'display_order' => $data['display_order'] ?? $finding->display_order,
                'is_public' => array_key_exists('is_public', $data) ? (bool) $data['is_public'] : $finding->is_public,
            ]);

            if (isset($data['evidence_ids'])) {
                $finding->evidence()->sync($data['evidence_ids']);
            }

            if (isset($data['hearing_entry_ids'])) {
                $finding->hearingEntries()->sync($data['hearing_entry_ids']);
            }

            if (isset($data['witness_ids'])) {
                $finding->witnesses()->sync($data['witness_ids']);
            }

            TribunalCaseEventService::log($case, 'finding_updated', $userId, [
                'finding_id' => $finding->id,
                'finding_number' => $finding->finding_number,
            ]);

            return $finding->load(['evidence', 'witnesses', 'hearingEntries']);
        });
    }

    /**
     * Delete an existing finding while in draft stage.
     */
    public function deleteFinding(TribunalFinding $finding, int $userId): void
    {
        $case = $finding->case;
        $this->ensureAssignedJuryPanel($case, $userId);

        if ($case->finalDecision()->exists()) {
            abort(409, 'Cannot delete findings after a final Tribunal decision has been published.');
        }

        $findingId = $finding->id;
        $findingNumber = $finding->finding_number;

        $finding->delete();

        TribunalCaseEventService::log($case, 'finding_removed', $userId, [
            'finding_id' => $findingId,
            'finding_number' => $findingNumber,
        ]);
    }

    /**
     * Validate that referenced evidence, hearing entries, and witnesses all belong to this case.
     */
    protected function validateReferences(TribunalCase $case, array $data): void
    {
        if (!empty($data['evidence_ids'])) {
            $invalidEv = TribunalEvidence::whereIn('id', $data['evidence_ids'])
                ->where('tribunal_case_id', '!=', $case->id)
                ->exists();
            if ($invalidEv) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'evidence_ids' => ['Referenced evidence does not belong to this tribunal case.'],
                ]);
            }
        }

        if (!empty($data['hearing_entry_ids'])) {
            $invalidEntries = TribunalHearingEntry::whereIn('id', $data['hearing_entry_ids'])
                ->whereHas('hearing', function ($q) use ($case) {
                    $q->where('tribunal_case_id', '!=', $case->id);
                })
                ->exists();
            if ($invalidEntries) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'hearing_entry_ids' => ['Referenced hearing entry does not belong to this tribunal case.'],
                ]);
            }
        }

        if (!empty($data['witness_ids'])) {
            $invalidWit = TribunalWitness::whereIn('id', $data['witness_ids'])
                ->where('tribunal_case_id', '!=', $case->id)
                ->exists();
            if ($invalidWit) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'witness_ids' => ['Referenced witness does not belong to this tribunal case.'],
                ]);
            }
        }
    }
}
