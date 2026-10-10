<?php

namespace App\Services\Tribunal;

use App\Enums\TribunalEvidenceChallengeStatus;
use App\Enums\TribunalEvidenceStatus;
use App\Enums\TribunalEvidenceType;
use App\Enums\TribunalPartyRole;
use App\Models\TribunalCase;
use App\Models\TribunalEvidence;
use App\Models\TribunalEvidenceChallenge;
use App\Notifications\Tribunal\TribunalEvidenceAddedNotification;
use App\Notifications\Tribunal\TribunalEvidenceChallengedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TribunalEvidenceService
{
    private const PRIVATE_DISK = 'local';
    private const STORAGE_DIR = 'tribunal_evidence';

    public function uploadEvidence(
        TribunalCase $tribunalCase,
        array $data,
        ?UploadedFile $file,
        int $userId
    ): TribunalEvidence {
        $this->authorizeParty($tribunalCase, $userId);

        return DB::transaction(function () use ($tribunalCase, $data, $file, $userId) {
            // Lock and generate sequential evidence number
            $lastEvidence = TribunalEvidence::where('tribunal_case_id', $tribunalCase->id)
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            $nextIndex = 1;
            if ($lastEvidence && preg_match('/EV-(\d+)/', $lastEvidence->evidence_number, $matches)) {
                $nextIndex = ((int) $matches[1]) + 1;
            }
            $evidenceNumber = sprintf('EV-%04d', $nextIndex);

            $fileDetails = [
                'original_filename' => null,
                'stored_filename' => null,
                'file_path' => null,
                'mime_type' => null,
                'file_size' => null,
                'sha256_hash' => null,
            ];

            if ($file instanceof UploadedFile) {
                $fileDetails = $this->storeFileSecurely($tribunalCase->id, $file);
            }

            $evidence = TribunalEvidence::create([
                'tribunal_case_id' => $tribunalCase->id,
                'uploaded_by' => $userId,
                'evidence_number' => $evidenceNumber,
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'original_filename' => $fileDetails['original_filename'],
                'stored_filename' => $fileDetails['stored_filename'],
                'file_path' => $fileDetails['file_path'],
                'mime_type' => $fileDetails['mime_type'],
                'file_size' => $fileDetails['file_size'],
                'sha256_hash' => $fileDetails['sha256_hash'],
                'external_url' => $data['external_url'] ?? null,
                'status' => TribunalEvidenceStatus::Submitted,
                'submitted_at' => now(),
            ]);

            // Log event
            TribunalCaseEventService::log($tribunalCase, 'evidence_uploaded', $userId, [
                'evidence_id' => $evidence->id,
                'evidence_number' => $evidence->evidence_number,
                'title' => $evidence->title,
                'type' => $evidence->type instanceof \BackedEnum ? $evidence->type->value : $evidence->type,
            ]);

            // Notify opposing party
            $this->notifyOpposingParty($tribunalCase, $evidence, $userId);

            return $evidence->load(['uploader.profile', 'challenges.challenger.profile']);
        });
    }

    public function challengeEvidence(
        TribunalCase $tribunalCase,
        TribunalEvidence $evidence,
        string $reason,
        int $userId
    ): TribunalEvidenceChallenge {
        $this->authorizeParty($tribunalCase, $userId);

        abort_if(
            $evidence->uploaded_by === $userId,
            403,
            'You cannot challenge your own evidence.'
        );

        $hasExistingPending = $evidence->challenges()
            ->where('challenged_by', $userId)
            ->where('status', TribunalEvidenceChallengeStatus::Pending)
            ->exists();

        abort_if(
            $hasExistingPending,
            422,
            'You have already submitted a pending challenge for this evidence item.'
        );

        return DB::transaction(function () use ($tribunalCase, $evidence, $reason, $userId) {
            $challenge = TribunalEvidenceChallenge::create([
                'tribunal_evidence_id' => $evidence->id,
                'challenged_by' => $userId,
                'reason' => $reason,
                'status' => TribunalEvidenceChallengeStatus::Pending,
            ]);

            $evidence->update([
                'status' => TribunalEvidenceStatus::Challenged,
            ]);

            // Log event
            TribunalCaseEventService::log($tribunalCase, 'evidence_challenged', $userId, [
                'evidence_id' => $evidence->id,
                'evidence_number' => $evidence->evidence_number,
                'challenge_id' => $challenge->id,
            ]);

            // Notify uploader
            $evidence->uploader?->notify(
                new TribunalEvidenceChallengedNotification($tribunalCase, $evidence, $challenge)
            );

            // Notify active juror if assigned and accepted
            $activeJuror = $tribunalCase->acceptedJuryAssignment?->juror;
            $activeJuror?->notify(
                new TribunalEvidenceChallengedNotification($tribunalCase, $evidence, $challenge)
            );

            return $challenge->load('challenger.profile');
        });
    }

    public function downloadEvidence(
        TribunalCase $tribunalCase,
        TribunalEvidence $evidence,
        int $userId
    ): StreamedResponse|BinaryFileResponse {
        $this->authorizeEvidenceAccess($tribunalCase, $userId);

        abort_unless(
            $evidence->file_path && Storage::disk(self::PRIVATE_DISK)->exists($evidence->file_path),
            404,
            'Evidence file not found.'
        );

        // Log download event
        TribunalCaseEventService::log($tribunalCase, 'evidence_downloaded', $userId, [
            'evidence_id' => $evidence->id,
            'evidence_number' => $evidence->evidence_number,
        ]);

        return Storage::disk(self::PRIVATE_DISK)->download(
            $evidence->file_path,
            $evidence->original_filename ?: $evidence->stored_filename
        );
    }

    public function authorizeEvidenceAccess(TribunalCase $tribunalCase, int $userId): void
    {
        $isParty = $tribunalCase->isParticipant($userId);
        $isAcceptedJuror = $tribunalCase->isAcceptedJuror($userId);
        $isAcceptedRepresentative = $tribunalCase->isAcceptedRepresentative($userId);
        $isAssignedJuryPanel = $tribunalCase->isAssignedJuryPanelUser($userId);

        abort_unless(
            $isParty || $isAcceptedJuror || $isAcceptedRepresentative || $isAssignedJuryPanel,
            403,
            'You are not authorized to view evidence for this case.'
        );
    }

    public function authorizeParty(TribunalCase $tribunalCase, int $userId): void
    {
        $isParty = $tribunalCase->isParticipant($userId);
        $isAcceptedRepresentative = $tribunalCase->isAcceptedRepresentative($userId);

        abort_unless(
            $isParty || $isAcceptedRepresentative,
            403,
            'Only case participants or authorized legal representatives can perform this action.'
        );
    }

    private function storeFileSecurely(int $caseId, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedFilename = Str::uuid()->toString() . '.' . $extension;
        $subDirectory = self::STORAGE_DIR . '/' . $caseId;
        $path = $file->storeAs($subDirectory, $storedFilename, self::PRIVATE_DISK);

        $realPath = $file->getRealPath();
        $hash = $realPath ? hash_file('sha256', $realPath) : null;

        return [
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'sha256_hash' => $hash,
        ];
    }

    private function notifyOpposingParty(TribunalCase $case, TribunalEvidence $evidence, int $uploaderId): void
    {
        $opposingParty = $case->parties()
            ->where('user_id', '!=', $uploaderId)
            ->first();

        $opposingUser = $opposingParty?->user;
        $opposingUser?->notify(new TribunalEvidenceAddedNotification($case, $evidence));
    }
}
