<?php

namespace App\Services\Professional;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalAdjudicatorStatus;
use App\Enums\TribunalJurorEligibilityStatus;
use App\Enums\TribunalQualificationStatus;
use App\Models\ProfessionalVerification;
use App\Models\TribunalAdjudicatorProfile;
use App\Models\TribunalJurorProfile;
use App\Models\User;
use App\Notifications\Professional\ProfessionalVerificationApprovedNotification;
use App\Notifications\Professional\ProfessionalVerificationRejectedNotification;
use App\Notifications\Professional\ProfessionalVerificationSubmittedNotification;
use App\Notifications\Professional\ProfessionalVerificationSuspendedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfessionalVerificationService
{
    private const PRIVATE_DISK = 'local';
    private const STORAGE_DIR = 'professional_verifications';

    /**
     * Submit a new professional verification application.
     */
    public function apply(User $user, array $data, array $files): ProfessionalVerification
    {
        return DB::transaction(function () use ($user, $data, $files) {
            $qualDocPath = $this->storeDocumentSecurely($user->id, $files['qualification_document']);
            $idDocPath = $this->storeDocumentSecurely($user->id, $files['identity_document']);
            $additionalDocPath = isset($files['additional_document']) && $files['additional_document'] instanceof UploadedFile
                ? $this->storeDocumentSecurely($user->id, $files['additional_document'])
                : null;

            $verification = ProfessionalVerification::create([
                'user_id' => $user->id,
                'profession_type' => $data['profession_type'],
                'verification_status' => ProfessionalVerificationStatus::Pending,
                'registration_number' => $data['registration_number'] ?? null,
                'enrollment_number' => $data['enrollment_number'] ?? null,
                'issuing_authority' => $data['issuing_authority'],
                'years_of_experience' => (int) $data['years_of_experience'],
                'qualification_document_path' => $qualDocPath,
                'identity_document_path' => $idDocPath,
                'additional_document_path' => $additionalDocPath,
                'submitted_at' => now(),
            ]);

            // Audit event log
            ProfessionalVerificationEventService::log($verification, 'application_created', $user->id);
            ProfessionalVerificationEventService::log($verification, 'documents_uploaded', $user->id);
            ProfessionalVerificationEventService::log($verification, 'submitted', $user->id);

            // Notify user
            $user->notify(new ProfessionalVerificationSubmittedNotification($verification));

            return $verification->load('user.profile');
        });
    }

    /**
     * Approve a verification application (Admin only).
     */
    public function approve(ProfessionalVerification $verification, User $reviewer): ProfessionalVerification
    {
        abort_unless($reviewer->isAdmin(), 403, 'Unauthorized. Admin privileges required.');

        return DB::transaction(function () use ($verification, $reviewer) {
            $verification->update([
                'verification_status' => ProfessionalVerificationStatus::Verified,
                'verified_at' => now(),
                'reviewed_at' => now(),
                'verified_by' => $reviewer->id,
                'rejection_reason' => null,
            ]);

            ProfessionalVerificationEventService::log($verification, 'approved', $reviewer->id);

            // If profession is eligible for adjudication, provision/update adjudicator profile
            $professionType = $verification->profession_type instanceof ProfessionalType
                ? $verification->profession_type
                : ProfessionalType::from($verification->profession_type);

            if ($professionType->isAdjudicatorEligible()) {
                TribunalAdjudicatorProfile::updateOrCreate(
                    ['user_id' => $verification->user_id],
                    [
                        'professional_verification_id' => $verification->id,
                        'status' => TribunalAdjudicatorStatus::Pending,
                        'qualification_status' => TribunalQualificationStatus::Pending,
                        'available' => false,
                        'notes' => "Auto-provisioned upon verification approval by Admin #{$reviewer->id}. Awaiting Tribunal qualification.",
                    ]
                );

                // Sync legacy profile table for backwards-compatibility
                TribunalJurorProfile::updateOrCreate(
                    ['user_id' => $verification->user_id],
                    [
                        'status' => TribunalJurorEligibilityStatus::Eligible,
                        'available' => true,
                        'qualified_at' => now(),
                        'training_completed_at' => now(),
                        'notes' => 'Verified legal professional adjudicator.',
                    ]
                );
            }

            // Notify applicant
            $verification->user?->notify(new ProfessionalVerificationApprovedNotification($verification));

            return $verification->load(['user.profile', 'reviewer.profile']);
        });
    }

    /**
     * Reject a verification application (Admin only).
     */
    public function reject(ProfessionalVerification $verification, User $reviewer, string $reason): ProfessionalVerification
    {
        abort_unless($reviewer->isAdmin(), 403, 'Unauthorized. Admin privileges required.');

        return DB::transaction(function () use ($verification, $reason, $reviewer) {
            $verification->update([
                'verification_status' => ProfessionalVerificationStatus::Rejected,
                'reviewed_at' => now(),
                'verified_by' => $reviewer->id,
                'rejection_reason' => $reason,
            ]);

            ProfessionalVerificationEventService::log($verification, 'rejected', $reviewer->id, [
                'reason' => $reason,
            ]);

            // Disable adjudicator profile if active
            $profile = TribunalAdjudicatorProfile::where('user_id', $verification->user_id)->first();
            if ($profile) {
                $profile->update([
                    'status' => TribunalAdjudicatorStatus::Inactive,
                    'available' => false,
                ]);
            }

            $jurorProfile = TribunalJurorProfile::where('user_id', $verification->user_id)->first();
            if ($jurorProfile) {
                $jurorProfile->update([
                    'status' => TribunalJurorEligibilityStatus::Inactive,
                    'available' => false,
                ]);
            }

            // Notify applicant
            $verification->user?->notify(new ProfessionalVerificationRejectedNotification($verification));

            return $verification->load(['user.profile', 'reviewer.profile']);
        });
    }

    /**
     * Suspend a verified professional (Admin only).
     */
    public function suspend(ProfessionalVerification $verification, User $reviewer, string $reason): ProfessionalVerification
    {
        abort_unless($reviewer->isAdmin(), 403, 'Unauthorized. Admin privileges required.');

        return DB::transaction(function () use ($verification, $reason, $reviewer) {
            $verification->update([
                'verification_status' => ProfessionalVerificationStatus::Suspended,
                'suspension_reason' => $reason,
            ]);

            ProfessionalVerificationEventService::log($verification, 'suspended', $reviewer->id, [
                'reason' => $reason,
            ]);

            // Suspend adjudicator profile
            $profile = TribunalAdjudicatorProfile::where('user_id', $verification->user_id)->first();
            if ($profile) {
                $profile->update([
                    'status' => TribunalAdjudicatorStatus::Suspended,
                    'available' => false,
                    'suspended_at' => now(),
                    'suspension_reason' => $reason,
                ]);
            }

            $jurorProfile = TribunalJurorProfile::where('user_id', $verification->user_id)->first();
            if ($jurorProfile) {
                $jurorProfile->update([
                    'status' => TribunalJurorEligibilityStatus::Suspended,
                    'available' => false,
                ]);
            }

            // Notify applicant after outermost commit
            DB::afterCommit(function () use ($verification) {
                $verification->user?->notify(new ProfessionalVerificationSuspendedNotification($verification));
            });

            return $verification->load(['user.profile', 'reviewer.profile']);
        });
    }

    /**
     * Download or view credential document securely.
     */
    public function downloadDocument(
        ProfessionalVerification $verification,
        string $documentType,
        User $requester
    ): StreamedResponse|BinaryFileResponse {
        $isOwner = $verification->user_id === $requester->id;
        $isAdmin = $requester->isAdmin();

        abort_unless($isOwner || $isAdmin, 403, 'Unauthorized to view these credential documents.');

        $path = match ($documentType) {
            'qualification' => $verification->qualification_document_path,
            'identity' => $verification->identity_document_path,
            'additional' => $verification->additional_document_path,
            default => null,
        };

        abort_unless($path && Storage::disk(self::PRIVATE_DISK)->exists($path), 404, 'Document file not found.');

        $filename = "{$documentType}_doc_{$verification->user_id}." . pathinfo($path, PATHINFO_EXTENSION);

        return Storage::disk(self::PRIVATE_DISK)->download($path, $filename);
    }

    private function storeDocumentSecurely(int $userId, UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $storedFilename = Str::uuid()->toString() . '.' . $ext;
        $dir = self::STORAGE_DIR . '/' . $userId;

        return $file->storeAs($dir, $storedFilename, self::PRIVATE_DISK);
    }
}
