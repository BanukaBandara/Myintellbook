<?php

namespace App\Services\Tribunal;

use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalPartyRole;
use App\Models\TribunalCase;
use Illuminate\Support\Facades\DB;

class TribunalCaseService
{
    public function __construct(
        protected TribunalJuryPanelAssignmentService $juryPanelAssignmentService
    ) {
    }

    public function create(array $data, int $userId): TribunalCase
    {
        // Revalidate respondent eligibility to prevent forged requests
        app(\App\Services\Tribunal\TribunalRespondentSearchService::class)
            ->validateEligibility((int) $data['respondent_id'], $userId);

        return DB::transaction(function () use ($data, $userId) {

            $case = TribunalCase::create([
                'created_by' => $userId,
                'title' => $data['title'],
                'category' => $data['category'],
                'description' => $data['description'],
                'requested_resolution' =>
                    $data['requested_resolution'] ?? null,
                'status' => TribunalCaseStatus::Submitted,
                'severity' => 'low',
                'submitted_at' => now(),
            ]);

            $case->update([
                'case_number' => sprintf(
                    'MIB-TRB-%s-%06d',
                    now()->year,
                    $case->id
                ),
            ]);

            $case->parties()->create([
                'user_id' => $userId,
                'role' => TribunalPartyRole::Complainant,
            ]);

            $case->parties()->create([
                'user_id' => $data['respondent_id'],
                'role' => TribunalPartyRole::Respondent,
            ]);

            // Notify respondent
            $respondent = \App\Models\User::find($data['respondent_id']);
            $respondent?->notify(new \App\Notifications\Tribunal\TribunalCaseFiledNotification($case));

            TribunalCaseEventService::log($case, 'case_created', $userId, [
                'case_number' => $case->case_number,
                'title' => $case->title,
                'category' => $case->category,
            ]);

            // Attempt immediate Jury Panel assignment upon case creation
            $this->juryPanelAssignmentService->assignCase($case);

            return $case->refresh()->load([
                'creator.profile',
                'parties.user.profile',
                'response',
                'currentJuryPanelAssignment.juryPanel',
            ]);
        });
    }
}
