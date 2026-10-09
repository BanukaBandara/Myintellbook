<?php

namespace Tests\Feature\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Enums\InternalReportCategory;
use App\Enums\InternalReportStatus;
use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalConversationType;
use App\Enums\TribunalPartyRole;
use App\Enums\TribunalRepresentationRequestStatus;
use App\Enums\TribunalRepresentativeAssignmentStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\InternalPenalty;
use App\Models\InternalReport;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\TribunalCase;
use App\Models\TribunalConversation;
use App\Models\TribunalJuryPanel;
use App\Models\TribunalRepresentationRequest;
use App\Models\TribunalRepresentativeAssignment;
use App\Models\User;
use App\Models\profession;
use App\Notifications\InternalTribunal\ReportedUserProfessionalDisciplineNotification;
use App\Notifications\Professional\ProfessionalVerificationSuspendedNotification;
use App\Notifications\Tribunal\TribunalRepresentationEndedNotification;
use App\Services\InternalTribunal\AccountProfessionalDisciplineService;
use App\Services\InternalTribunal\InternalReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfessionalDisciplinePenaltyTest extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
        Storage::fake('local');

        $cat = Category::create(['name' => 'Legal Services']);
        $prof = profession::create([
            'category_id' => $cat->id,
            'name' => 'Attorney at Law',
        ]);
        $this->professionId = $prof->id;
    }

    protected function createUser(string $email, bool $isAdmin = false): User
    {
        $suffix = Str::lower(Str::random(6));

        $user = User::create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'is_admin' => $isAdmin,
        ]);

        Profile::create([
            'user_id' => $user->id,
            'first_name' => "First_{$suffix}",
            'last_name' => "Last_{$suffix}",
            'gender' => 1,
            'profession_id' => $this->professionId,
            'birth_date' => '1990-01-01',
            'slug' => "user_{$suffix}",
        ]);

        return $user;
    }

    protected function createVerifiedAttorney(string $email): User
    {
        $user = $this->createUser($email);

        ProfessionalVerification::create([
            'user_id' => $user->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'registration_number' => 'BAR-' . Str::upper(Str::random(6)),
            'issuing_authority' => 'Supreme Court Bar Association',
            'years_of_experience' => 5,
            'verification_status' => ProfessionalVerificationStatus::Verified,
            'verified_at' => now(),
            'reviewed_at' => now(),
        ]);

        return $user;
    }

    protected function authHeaders(User $user, ?string &$rawTokenOut = null): array
    {
        $rawToken = 'tok_' . Str::random(40);
        $rawTokenOut = $rawToken;

        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'Authorization' => "Bearer {$rawToken}",
            'Accept' => 'application/json',
        ];
    }

    protected function createValidReport(User $reporter, User $reportedUser): InternalReport
    {
        $reportService = app(InternalReportService::class);
        $report = $reportService->createReport([
            'reported_user_id' => $reportedUser->id,
            'category' => InternalReportCategory::QualificationProfessionalFraud->value,
            'subject' => 'Professional misconduct alleged',
            'description' => 'Detailed misconduct report for test.',
            'severity' => 'high',
        ], [], $reporter);

        $report->update(['status' => InternalReportStatus::Valid->value]);

        return $report->fresh();
    }

    // -------------------------------------------------------------------------
    // VERIFICATION REVOKED
    // -------------------------------------------------------------------------

    #[Test]
    public function test_super_admin_can_apply_verification_revoked_to_valid_professional(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin_vr@test.com', isAdmin: true);
        $reporter = $this->createUser('reporter_vr@test.com');
        $lawyer = $this->createVerifiedAttorney('lawyer_vr@test.com');
        $report = $this->createValidReport($reporter, $lawyer);

        $res = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::VerificationRevoked->value,
            'reason' => 'Gross professional misconduct proven during investigation.',
            'notes' => 'Internal committee confirmation.',
        ], $this->authHeaders($admin));

        $res->assertStatus(200);
        $this->assertEquals(InternalPenaltyType::VerificationRevoked->value, $res->json('data.action_type'));

        // Underlying verification is now Suspended
        $this->assertEquals(ProfessionalVerificationStatus::Suspended, $lawyer->fresh()->latestProfessionalVerification->verification_status);

        // Login remains functional
        $tokenRes = $this->postJson('/api/login', [
            'email' => 'lawyer_vr@test.com',
            'password' => 'password123',
        ]);
        $tokenRes->assertStatus(200);

        // Single sanitized notification dispatched
        Notification::assertSentTo($lawyer, ProfessionalVerificationSuspendedNotification::class);
        Notification::assertNotSentTo($lawyer, ReportedUserProfessionalDisciplineNotification::class);
    }

    #[Test]
    public function test_verification_revoked_safely_terminates_active_representations(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin_rep@test.com', isAdmin: true);
        $client = $this->createUser('client_rep@test.com');
        $respondent = $this->createUser('resp_rep@test.com');
        $lawyer = $this->createVerifiedAttorney('lawyer_rep@test.com');
        $report = $this->createValidReport($client, $lawyer);

        // Create a tribunal case
        $case = TribunalCase::create([
            'case_number' => 'MIB-TRB-2026-999901',
            'title' => 'Commercial Contract Dispute',
            'category' => 'Financial Dispute',
            'description' => 'Test case description.',
            'created_by' => $client->id,
            'status' => TribunalCaseStatus::EvidenceCollection,
            'severity' => 'low',
        ]);

        $case->parties()->create(['user_id' => $client->id, 'role' => TribunalPartyRole::Complainant]);
        $case->parties()->create(['user_id' => $respondent->id, 'role' => TribunalPartyRole::Respondent]);

        // Active representation
        $assignment = TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $client->id,
            'representative_user_id' => $lawyer->id,
            'side' => TribunalPartyRole::Complainant,
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'accepted_at' => now(),
        ]);

        // Client-representative conversation
        $conversation = TribunalConversation::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $client->id,
            'representative_user_id' => $lawyer->id,
            'type' => TribunalConversationType::ComplainantRepresentative,
            'active' => true,
        ]);

        // Pending request for another case
        $pendingReq = TribunalRepresentationRequest::create([
            'tribunal_case_id' => $case->id,
            'requested_by' => $client->id,
            'client_user_id' => $client->id,
            'representative_user_id' => $lawyer->id,
            'side' => TribunalPartyRole::Complainant,
            'status' => TribunalRepresentationRequestStatus::Pending,
            'requested_at' => now(),
        ]);

        // Apply Verification Revoked
        $res = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::VerificationRevoked->value,
            'reason' => 'Disbarred by state licensing body.',
        ], $this->authHeaders($admin));

        $res->assertStatus(200);

        // Assignment ended safely (not deleted)
        $freshAssignment = $assignment->fresh();
        $this->assertEquals(TribunalRepresentativeAssignmentStatus::Ended, $freshAssignment->status);
        $this->assertNotNull($freshAssignment->ended_at);
        $this->assertEquals($admin->id, $freshAssignment->ended_by);
        $this->assertStringContainsString('administrative review', $freshAssignment->end_reason);

        // Conversation deactivated
        $this->assertFalse((bool) $conversation->fresh()->active);

        // Pending request cancelled
        $this->assertEquals(TribunalRepresentationRequestStatus::Cancelled, $pendingReq->fresh()->status);

        // Client notified
        Notification::assertSentTo($client, TribunalRepresentationEndedNotification::class);
    }

    #[Test]
    public function test_verification_revoked_validation_and_immunities(): void
    {
        $admin = $this->createUser('admin_imm@test.com', isAdmin: true);
        $lawyer = $this->createVerifiedAttorney('lawyer_imm@test.com');
        $nonAdmin = $this->createUser('nonadmin_imm@test.com');
        $unverified = $this->createUser('unverif_imm@test.com');

        // Non-admin cannot apply
        $report1 = $this->createValidReport($nonAdmin, $lawyer);
        $this->postJson("/api/admin/internal-reports/{$report1->id}/penalties", [
            'action_type' => InternalPenaltyType::VerificationRevoked->value,
            'reason' => 'Unauthorized attempt.',
        ], $this->authHeaders($nonAdmin))->assertStatus(403);

        // Cannot apply to unverified user
        $report2 = $this->createValidReport($admin, $unverified);
        $this->postJson("/api/admin/internal-reports/{$report2->id}/penalties", [
            'action_type' => InternalPenaltyType::VerificationRevoked->value,
            'reason' => 'Target has no verification.',
        ], $this->authHeaders($admin))->assertStatus(422);

        // Cannot apply to Super Admin
        $targetAdmin = $this->createVerifiedAttorney('target_admin@test.com');
        $targetAdmin->update(['is_admin' => true]);
        $report3 = $this->createValidReport($admin, $targetAdmin);
        $this->postJson("/api/admin/internal-reports/{$report3->id}/penalties", [
            'action_type' => InternalPenaltyType::VerificationRevoked->value,
            'reason' => 'Target is admin.',
        ], $this->authHeaders($admin))->assertStatus(422);

        // Non-Valid report rejected
        $report4 = $this->createValidReport($admin, $lawyer);
        $report4->update(['status' => InternalReportStatus::UnderReview->value]);
        $this->postJson("/api/admin/internal-reports/{$report4->id}/penalties", [
            'action_type' => InternalPenaltyType::VerificationRevoked->value,
            'reason' => 'Report not valid yet.',
        ], $this->authHeaders($admin))->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // CONSERVATIVE REVERSAL OF VERIFICATION REVOKED
    // -------------------------------------------------------------------------

    #[Test]
    public function test_verification_revoked_reversal_is_conservative(): void
    {
        $admin = $this->createUser('admin_rev@test.com', isAdmin: true);
        $lawyer = $this->createVerifiedAttorney('lawyer_rev@test.com');
        $report = $this->createValidReport($admin, $lawyer);

        // Apply Verification Revoked
        $res = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::VerificationRevoked->value,
            'reason' => 'Disciplinary sanction.',
        ], $this->authHeaders($admin));
        $res->assertStatus(200);
        $penaltyId = $res->json('data.penalty_id');

        $this->assertEquals(ProfessionalVerificationStatus::Suspended, $lawyer->fresh()->latestProfessionalVerification->verification_status);

        // Reverse penalty
        $revRes = $this->postJson("/api/admin/internal-reports/penalties/{$penaltyId}/reverse", [
            'reversal_reason' => 'New exonerating evidence received.',
        ], $this->authHeaders($admin));
        $revRes->assertStatus(200);

        // Audit written
        $this->assertDatabaseHas('internal_report_audits', [
            'internal_report_id' => $report->id,
            'action' => 'Penalty Reversed: Verification Revoked',
        ]);

        // Conservative strategy: Verification remains Suspended!
        $this->assertEquals(ProfessionalVerificationStatus::Suspended, $lawyer->fresh()->latestProfessionalVerification->verification_status);
        $this->assertFalse($lawyer->fresh()->canActAsLegalRepresentative());
    }

    // -------------------------------------------------------------------------
    // PROFESSIONAL ELIGIBILITY SUSPENSION
    // -------------------------------------------------------------------------

    #[Test]
    public function test_professional_eligibility_suspension_temporary_and_permanent(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin_pes@test.com', isAdmin: true);
        $lawyer = $this->createVerifiedAttorney('lawyer_pes@test.com');
        $report = $this->createValidReport($admin, $lawyer);

        // Apply temporary suspension
        $res = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::ProfessionalEligibilitySuspension->value,
            'restriction_duration_type' => 'temporary',
            'duration_days' => 14,
            'reason' => 'Administrative suspension pending inquiry.',
        ], $this->authHeaders($admin));

        $res->assertStatus(200);
        $this->assertEquals(InternalPenaltyType::ProfessionalEligibilitySuspension->value, $res->json('data.action_type'));

        // Underlying verification remains Verified!
        $this->assertEquals(ProfessionalVerificationStatus::Verified, $lawyer->fresh()->latestProfessionalVerification->verification_status);

        // Dynamic discipline service returns true
        $disciplineService = app(AccountProfessionalDisciplineService::class);
        $this->assertTrue($disciplineService->hasActiveEligibilitySuspension($lawyer));

        // Sanitized notification sent
        Notification::assertSentTo($lawyer, ReportedUserProfessionalDisciplineNotification::class, function ($n) {
            $data = $n->toDatabase(new User());
            return $data['is_temporary'] === true && isset($data['ends_at']) && !isset($data['reason']);
        });
    }

    #[Test]
    public function test_professional_eligibility_suspension_enforcement_in_representation(): void
    {
        $admin = $this->createUser('admin_enf@test.com', isAdmin: true);
        $client = $this->createUser('client_enf@test.com');
        $respondent = $this->createUser('resp_enf@test.com');
        $lawyer = $this->createVerifiedAttorney('lawyer_enf@test.com');
        $report = $this->createValidReport($admin, $lawyer);

        // Apply suspension
        $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::ProfessionalEligibilitySuspension->value,
            'restriction_duration_type' => 'temporary',
            'duration_days' => 7,
            'reason' => 'Temporary eligibility restriction.',
        ], $this->authHeaders($admin))->assertStatus(200);

        // Directory excludes lawyer
        $repList = $this->getJson('/api/tribunal/representatives', $this->authHeaders($client));
        $repList->assertStatus(200);
        $lawyerIds = collect($repList->json('data'))->pluck('id')->all();
        $this->assertNotContains($lawyer->id, $lawyerIds);

        // Case for representation request
        $case = TribunalCase::create([
            'case_number' => 'MIB-TRB-2026-999902',
            'title' => 'Contract Dispute',
            'category' => 'Financial Dispute',
            'description' => 'Test case description.',
            'created_by' => $client->id,
            'status' => TribunalCaseStatus::EvidenceCollection,
            'severity' => 'low',
        ]);
        $case->parties()->create(['user_id' => $client->id, 'role' => TribunalPartyRole::Complainant]);
        $case->parties()->create(['user_id' => $respondent->id, 'role' => TribunalPartyRole::Respondent]);

        // Direct requestRepresentation rejected with 422
        $reqRes = $this->postJson("/api/tribunal/cases/{$case->id}/representation-requests", [
            'representative_user_id' => $lawyer->id,
            'message' => 'Please represent me.',
        ], $this->authHeaders($client));
        $reqRes->assertStatus(422);

        // /api/tribunal/me reflects restricted capability
        $meRes = $this->getJson('/api/tribunal/me', $this->authHeaders($lawyer));
        $meRes->assertStatus(200);
        $this->assertFalse($meRes->json('can_act_as_representative'));
        $this->assertFalse($meRes->json('representative.eligible'));
    }

    #[Test]
    public function test_existing_active_representation_continues_during_eligibility_suspension(): void
    {
        $admin = $this->createUser('admin_cont@test.com', isAdmin: true);
        $client = $this->createUser('client_cont@test.com');
        $respondent = $this->createUser('resp_cont@test.com');
        $lawyer = $this->createVerifiedAttorney('lawyer_cont@test.com');
        $report = $this->createValidReport($admin, $lawyer);

        $case = TribunalCase::create([
            'case_number' => 'MIB-TRB-2026-999903',
            'title' => 'Contract Dispute',
            'category' => 'Financial Dispute',
            'description' => 'Test case description.',
            'created_by' => $client->id,
            'status' => TribunalCaseStatus::EvidenceCollection,
            'severity' => 'low',
        ]);
        $case->parties()->create(['user_id' => $client->id, 'role' => TribunalPartyRole::Complainant]);
        $case->parties()->create(['user_id' => $respondent->id, 'role' => TribunalPartyRole::Respondent]);

        // Pre-existing active representation
        $assignment = TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $client->id,
            'representative_user_id' => $lawyer->id,
            'side' => TribunalPartyRole::Complainant,
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'accepted_at' => now(),
        ]);

        // Apply Professional Eligibility Suspension
        $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::ProfessionalEligibilitySuspension->value,
            'restriction_duration_type' => 'temporary',
            'duration_days' => 10,
            'reason' => 'Administrative suspension.',
        ], $this->authHeaders($admin))->assertStatus(200);

        // Existing representation assignment remains active
        $this->assertEquals(TribunalRepresentativeAssignmentStatus::Active, $assignment->fresh()->status);
        $this->assertTrue($case->fresh()->isAcceptedRepresentative($lawyer->id));
    }

    // -------------------------------------------------------------------------
    // EXPIRATION AND REVERSAL DYNAMICS
    // -------------------------------------------------------------------------

    #[Test]
    public function test_temporary_eligibility_suspension_expires_automatically(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00'));

        $admin = $this->createUser('admin_exp@test.com', isAdmin: true);
        $lawyer = $this->createVerifiedAttorney('lawyer_exp@test.com');
        $report = $this->createValidReport($admin, $lawyer);

        $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::ProfessionalEligibilitySuspension->value,
            'restriction_duration_type' => 'temporary',
            'duration_days' => 5,
            'reason' => '5-day inquiry suspension.',
        ], $this->authHeaders($admin))->assertStatus(200);

        $disciplineService = app(AccountProfessionalDisciplineService::class);
        $this->assertTrue($disciplineService->hasActiveEligibilitySuspension($lawyer));

        // Advance time 6 days into the future
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00'));

        // Automatically resumes without DB writes
        $this->assertFalse($disciplineService->hasActiveEligibilitySuspension($lawyer));

        // Lawyer reappears in directory
        $client = $this->createUser('client_exp@test.com');
        $repList = $this->getJson('/api/tribunal/representatives', $this->authHeaders($client));
        $lawyerIds = collect($repList->json('data'))->pluck('id')->all();
        $this->assertContains($lawyer->id, $lawyerIds);

        Carbon::setTestNow();
    }

    #[Test]
    public function test_reversing_eligibility_suspension_resumes_eligibility_instantly(): void
    {
        $admin = $this->createUser('admin_rev_pes@test.com', isAdmin: true);
        $lawyer = $this->createVerifiedAttorney('lawyer_rev_pes@test.com');
        $report = $this->createValidReport($admin, $lawyer);

        $res = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::ProfessionalEligibilitySuspension->value,
            'restriction_duration_type' => 'permanent',
            'reason' => 'Indefinite suspension.',
        ], $this->authHeaders($admin));
        $res->assertStatus(200);
        $penaltyId = $res->json('data.penalty_id');

        $disciplineService = app(AccountProfessionalDisciplineService::class);
        $this->assertTrue($disciplineService->hasActiveEligibilitySuspension($lawyer));

        // Admin reverses
        $this->postJson("/api/admin/internal-reports/penalties/{$penaltyId}/reverse", [
            'reversal_reason' => 'Inquiry cleared.',
        ], $this->authHeaders($admin))->assertStatus(200);

        $this->assertFalse($disciplineService->hasActiveEligibilitySuspension($lawyer));
    }

    // -------------------------------------------------------------------------
    // DUPLICATE PROTECTION
    // -------------------------------------------------------------------------

    #[Test]
    public function test_duplicate_active_penalties_are_prevented(): void
    {
        $admin = $this->createUser('admin_dup@test.com', isAdmin: true);
        $lawyer = $this->createVerifiedAttorney('lawyer_dup@test.com');
        $report1 = $this->createValidReport($admin, $lawyer);
        $report2 = $this->createValidReport($admin, $lawyer);

        // Apply Eligibility Suspension
        $this->postJson("/api/admin/internal-reports/{$report1->id}/penalties", [
            'action_type' => InternalPenaltyType::ProfessionalEligibilitySuspension->value,
            'restriction_duration_type' => 'permanent',
            'reason' => 'First suspension.',
        ], $this->authHeaders($admin))->assertStatus(200);

        // Duplicate attempt rejected
        $this->postJson("/api/admin/internal-reports/{$report2->id}/penalties", [
            'action_type' => InternalPenaltyType::ProfessionalEligibilitySuspension->value,
            'restriction_duration_type' => 'temporary',
            'duration_days' => 10,
            'reason' => 'Second suspension attempt.',
        ], $this->authHeaders($admin))->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // TRANSACTION ROLLBACK SAFETY
    // -------------------------------------------------------------------------

    #[Test]
    public function test_suspension_called_inside_rolled_back_transaction_does_not_send_notification(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin_rb@test.com', isAdmin: true);
        $lawyer = $this->createVerifiedAttorney('lawyer_rb@test.com');
        $verification = $lawyer->latestProfessionalVerification;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($verification, $admin) {
                app(\App\Services\Professional\ProfessionalVerificationService::class)
                    ->suspend($verification, $admin, 'Testing rollback safety.');

                // Force an unexpected exception to trigger a rollback
                throw new \RuntimeException('Simulated transaction failure.');
            });
        } catch (\RuntimeException $e) {
            // Expected exception
        }

        // Suspension notification must NOT be dispatched because transaction rolled back
        Notification::assertNotSentTo($lawyer, ProfessionalVerificationSuspendedNotification::class);

        // Verification status rolled back
        $this->assertEquals(ProfessionalVerificationStatus::Verified, $verification->fresh()->verification_status);
    }
}
