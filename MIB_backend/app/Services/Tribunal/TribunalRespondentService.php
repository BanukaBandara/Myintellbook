<?php

namespace App\Services\Tribunal;

use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalPartyRole;
use App\Models\TribunalCase;
use App\Models\TribunalCaseResponse;
use App\Notifications\Tribunal\TribunalCaseAcknowledgedNotification;
use App\Notifications\Tribunal\TribunalCaseResponseSubmittedNotification;
use Illuminate\Support\Facades\DB;

class TribunalRespondentService
{
    public function __construct(
        private readonly JurySelectionService $jurySelectionService
    ) {
    }

    public function acknowledge(TribunalCase $tribunalCase, int $userId): TribunalCase
    {
        $isRespondent = $tribunalCase->parties()
            ->where('user_id', $userId)
            ->where('role', TribunalPartyRole::Respondent)
            ->exists();

        abort_unless($isRespondent, 403, 'Only the respondent can acknowledge this case.');

        DB::transaction(function () use ($tribunalCase, $userId) {
            $response = TribunalCaseResponse::firstOrCreate(
                ['tribunal_case_id' => $tribunalCase->id],
                [
                    'respondent_id' => $userId,
                    'acknowledgement_at' => now(),
                ]
            );

            if ($response->acknowledgement_at === null) {
                $response->update([
                    'acknowledgement_at' => now(),
                ]);
            }

            if ($tribunalCase->status === TribunalCaseStatus::Submitted) {
                $tribunalCase->update([
                    'status' => TribunalCaseStatus::AwaitingRespondent,
                ]);
            }

            // Log event
            TribunalCaseEventService::log($tribunalCase, 'case_acknowledged', $userId);

            // Notify complainant
            $complainantParty = $tribunalCase->parties()
                ->where('role', TribunalPartyRole::Complainant)
                ->first();
            $complainantUser = $complainantParty?->user ?? $tribunalCase->creator;
            $complainantUser?->notify(new TribunalCaseAcknowledgedNotification($tribunalCase));
        });

        return $tribunalCase->load([
            'creator.profile',
            'parties.user.profile',
            'response',
        ]);
    }

    public function submitResponse(TribunalCase $tribunalCase, array $data, int $userId): TribunalCase
    {
        $isRespondent = $tribunalCase->parties()
            ->where('user_id', $userId)
            ->where('role', TribunalPartyRole::Respondent)
            ->exists();

        abort_unless($isRespondent, 403, 'Only the respondent can submit a response to this case.');

        $existingResponse = $tribunalCase->response;
        if ($existingResponse && $existingResponse->submitted_at !== null) {
            abort(422, 'A response has already been submitted for this case.');
        }

        DB::transaction(function () use ($tribunalCase, $data, $userId, $existingResponse) {
            TribunalCaseResponse::updateOrCreate(
                ['tribunal_case_id' => $tribunalCase->id],
                [
                    'respondent_id' => $userId,
                    'acknowledgement_at' => $existingResponse?->acknowledgement_at ?? now(),
                    'position' => $data['position'],
                    'response_text' => $data['response_text'],
                    'submitted_at' => now(),
                ]
            );

            $tribunalCase->update([
                'status' => TribunalCaseStatus::JurySelection,
            ]);

            // Log event
            TribunalCaseEventService::log($tribunalCase, 'response_submitted', $userId, [
                'position' => $data['position'],
            ]);

            // Notify complainant
            $complainantParty = $tribunalCase->parties()
                ->where('role', TribunalPartyRole::Complainant)
                ->first();
            $complainantUser = $complainantParty?->user ?? $tribunalCase->creator;
            $complainantUser?->notify(new TribunalCaseResponseSubmittedNotification($tribunalCase));

            // Trigger random jury selection
            $this->jurySelectionService->assignJurorToCase($tribunalCase);
        });

        return $tribunalCase->load([
            'creator.profile',
            'parties.user.profile',
            'response',
            'currentJuryAssignment.juror.profile',
        ]);
    }
}
