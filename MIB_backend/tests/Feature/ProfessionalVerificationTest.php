<?php

namespace Tests\Feature;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalAdjudicatorStatus;
use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalJuryAssignmentStatus;
use App\Enums\TribunalPartyRole;
use App\Enums\TribunalQualificationStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\ProfessionalVerification;
use App\Models\profession;
use App\Models\Profile;
use App\Models\TribunalAdjudicatorProfile;
use App\Models\TribunalCase;
use App\Models\TribunalJuryAssignment;
use App\Models\User;
use App\Notifications\Professional\ProfessionalVerificationApprovedNotification;
use App\Notifications\Professional\ProfessionalVerificationRejectedNotification;
use App\Services\Tribunal\JurySelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfessionalVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        $cat = Category::create(['name' => 'Legal']);
        $prof = profession::create([
            'category_id' => $cat->id,
            'name' => 'Lawyer',
        ]);
        $this->professionId = $prof->id;
    }

    protected function createUser(string $email, bool $isAdmin = false, ?string $professionName = 'Lawyer'): User
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
        ]);

        return $user;
    }

    protected function authHeaders(User $user): array
    {
        $rawToken = 'token_' . Str::random(40);

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

    protected function createCase(User $complainant, User $respondent): TribunalCase
    {
        $case = TribunalCase::create([
            'created_by' => $complainant->id,
            'title' => 'Dispute Over Professional Contract',
            'category' => 'Commercial',
            'description' => 'Breach of agreement between parties.',
            'status' => TribunalCaseStatus::Submitted,
            'severity' => 'medium',
            'submitted_at' => now(),
        ]);

        $case->update([
            'case_number' => sprintf('MIB-TRB-%s-%06d', now()->year, $case->id),
        ]);

        $case->parties()->create([
            'user_id' => $complainant->id,
            'role' => TribunalPartyRole::Complainant,
        ]);

        $case->parties()->create([
            'user_id' => $respondent->id,
            'role' => TribunalPartyRole::Respondent,
        ]);

        return $case;
    }

    protected function makeVerifiedAdjudicator(
        User $user,
        ProfessionalType $profType = ProfessionalType::AttorneyAtLaw,
        TribunalQualificationStatus $qualStatus = TribunalQualificationStatus::Passed,
        TribunalAdjudicatorStatus $adjStatus = TribunalAdjudicatorStatus::Eligible,
        bool $available = true,
        ProfessionalVerificationStatus $verStatus = ProfessionalVerificationStatus::Verified,
        ?\DateTimeInterface $expiresAt = null,
        ?\DateTimeInterface $suspendedAt = null
    ): array {
        $pv = ProfessionalVerification::create([
            'user_id' => $user->id,
            'profession_type' => $profType,
            'verification_status' => $verStatus,
            'registration_number' => 'REG-987654',
            'enrollment_number' => 'ENR-123456',
            'issuing_authority' => 'High Court Bar Council',
            'years_of_experience' => 7,
            'submitted_at' => now()->subDays(5),
            'verified_at' => $verStatus === ProfessionalVerificationStatus::Verified ? now()->subDays(2) : null,
            'expires_at' => $expiresAt,
            'suspended_at' => $suspendedAt,
        ]);

        $profile = TribunalAdjudicatorProfile::create([
            'user_id' => $user->id,
            'professional_verification_id' => $pv->id,
            'status' => $adjStatus,
            'qualification_status' => $qualStatus,
            'qualification_score' => 88,
            'qualified_at' => in_array($qualStatus, [TribunalQualificationStatus::Passed, TribunalQualificationStatus::Exempted], true) ? now()->subDays(2) : null,
            'available' => $available,
            'suspended_at' => $suspendedAt,
        ]);

        return ['verification' => $pv, 'profile' => $profile];
    }

    #[Test]
    public function normal_user_can_submit_verification_application(): void
    {
        Storage::fake('local');

        $user = $this->createUser('lawyer.applicant@example.com');

        $qualificationDoc = UploadedFile::fake()->create('law_degree.pdf', 500, 'application/pdf');
        $identityDoc = UploadedFile::fake()->create('national_id.pdf', 300, 'application/pdf');

        $response = $this->withHeaders($this->authHeaders($user))
            ->post('/api/professional-verifications', [
                'profession_type' => 'attorney_at_law',
                'registration_number' => 'BAR-2026-1234',
                'enrollment_number' => 'ENR-8888',
                'issuing_authority' => 'State Bar Association',
                'years_of_experience' => 6,
                'qualification_document' => $qualificationDoc,
                'identity_document' => $identityDoc,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.profession_type', 'attorney_at_law');
        $response->assertJsonPath('data.verification_status', 'pending');
        $response->assertJsonPath('data.masked_registration_number', '****1234');

        $this->assertDatabaseHas('professional_verifications', [
            'user_id' => $user->id,
            'profession_type' => 'attorney_at_law',
            'verification_status' => 'pending',
            'registration_number' => 'BAR-2026-1234',
        ]);

        $this->assertDatabaseHas('professional_verification_events', [
            'event_type' => 'application_created',
            'actor_id' => $user->id,
        ]);
    }

    #[Test]
    public function normal_user_cannot_approve_verification(): void
    {
        $normalUser = $this->createUser('normal@example.com');
        $applicant = $this->createUser('applicant@example.com');

        $pv = ProfessionalVerification::create([
            'user_id' => $applicant->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Pending,
            'issuing_authority' => 'Bar Council',
            'years_of_experience' => 4,
            'submitted_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders($normalUser))
            ->postJson("/api/admin/professional-verifications/{$pv->id}/approve");

        $response->assertStatus(403);
        $this->assertEquals('pending', $pv->fresh()->verification_status->value);
    }

    #[Test]
    public function reviewer_can_approve_verification(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $applicant = $this->createUser('applicant2@example.com');

        $pv = ProfessionalVerification::create([
            'user_id' => $applicant->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Pending,
            'issuing_authority' => 'Supreme Court Bar',
            'years_of_experience' => 8,
            'submitted_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders($admin))
            ->postJson("/api/admin/professional-verifications/{$pv->id}/approve");

        $response->assertStatus(200);
        $response->assertJsonPath('data.verification_status', 'verified');
        $response->assertJsonPath('data.is_verified', true);

        $pvFresh = $pv->fresh();
        $this->assertEquals(ProfessionalVerificationStatus::Verified, $pvFresh->verification_status);
        $this->assertEquals($admin->id, $pvFresh->verified_by);
        $this->assertNotNull($pvFresh->verified_at);

        // Adjudicator profile was automatically provisioned
        $this->assertDatabaseHas('tribunal_adjudicator_profiles', [
            'user_id' => $applicant->id,
            'professional_verification_id' => $pv->id,
            'status' => TribunalAdjudicatorStatus::Pending->value,
        ]);

        Notification::assertSentTo($applicant, ProfessionalVerificationApprovedNotification::class);
    }

    #[Test]
    public function reviewer_can_reject_verification_with_reason(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin2@example.com', isAdmin: true);
        $applicant = $this->createUser('applicant3@example.com');

        $pv = ProfessionalVerification::create([
            'user_id' => $applicant->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Pending,
            'issuing_authority' => 'Bar Council',
            'years_of_experience' => 3,
            'submitted_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders($admin))
            ->postJson("/api/admin/professional-verifications/{$pv->id}/reject", [
                'rejection_reason' => 'Degree certificate cannot be verified with the issuing university.',
            ]);

        $response->assertStatus(200);
        $this->assertEquals(ProfessionalVerificationStatus::Rejected, $pv->fresh()->verification_status);
        $this->assertEquals('Degree certificate cannot be verified with the issuing university.', $pv->fresh()->rejection_reason);

        Notification::assertSentTo($applicant, ProfessionalVerificationRejectedNotification::class);
    }

    #[Test]
    public function profile_profession_alone_does_not_create_adjudicator_eligibility(): void
    {
        $complainant = $this->createUser('comp@example.com');
        $respondent = $this->createUser('resp@example.com');
        $case = $this->createCase($complainant, $respondent);

        // User who merely has "Lawyer" on their profile, but no professional verification
        $lawyerOnlyInProfile = $this->createUser('fake.lawyer@example.com', professionName: 'Lawyer');

        $service = app(JurySelectionService::class);
        $assignment = $service->assignJurorToCase($case);

        // Selection must NOT select this user
        $this->assertNull($assignment);
    }

    #[Test]
    public function verified_lawyer_with_failed_qualification_cannot_be_selected(): void
    {
        $complainant = $this->createUser('comp2@example.com');
        $respondent = $this->createUser('resp2@example.com');
        $case = $this->createCase($complainant, $respondent);

        $failedLawyer = $this->createUser('failed.lawyer@example.com');
        $this->makeVerifiedAdjudicator(
            $failedLawyer,
            profType: ProfessionalType::AttorneyAtLaw,
            qualStatus: TribunalQualificationStatus::Failed,
            adjStatus: TribunalAdjudicatorStatus::Pending,
            available: true
        );

        $service = app(JurySelectionService::class);
        $assignment = $service->assignJurorToCase($case);

        $this->assertNull($assignment);
    }

    #[Test]
    public function verified_lawyer_with_passed_qualification_and_eligible_and_available_can_be_selected(): void
    {
        $complainant = $this->createUser('comp3@example.com');
        $respondent = $this->createUser('resp3@example.com');
        $case = $this->createCase($complainant, $respondent);

        $eligibleLawyer = $this->createUser('eligible.lawyer@example.com');
        $this->makeVerifiedAdjudicator(
            $eligibleLawyer,
            profType: ProfessionalType::AttorneyAtLaw,
            qualStatus: TribunalQualificationStatus::Passed,
            adjStatus: TribunalAdjudicatorStatus::Eligible,
            available: true
        );

        $service = app(JurySelectionService::class);
        $assignment = $service->assignJurorToCase($case);

        $this->assertNotNull($assignment);
        $this->assertEquals($eligibleLawyer->id, $assignment->juror_id);
    }

    #[Test]
    public function complainant_and_respondent_excluded_from_selection_even_if_verified(): void
    {
        $complainant = $this->createUser('comp4@example.com');
        $respondent = $this->createUser('resp4@example.com');
        $case = $this->createCase($complainant, $respondent);

        // Both parties are verified attorneys with passed qualification
        $this->makeVerifiedAdjudicator($complainant);
        $this->makeVerifiedAdjudicator($respondent);

        $service = app(JurySelectionService::class);
        $assignment = $service->assignJurorToCase($case);

        // Neither complainant nor respondent can be selected
        $this->assertNull($assignment);
    }

    #[Test]
    public function suspended_professional_excluded_from_selection(): void
    {
        $complainant = $this->createUser('comp5@example.com');
        $respondent = $this->createUser('resp5@example.com');
        $case = $this->createCase($complainant, $respondent);

        $suspendedLawyer = $this->createUser('suspended.lawyer@example.com');
        $this->makeVerifiedAdjudicator(
            $suspendedLawyer,
            adjStatus: TribunalAdjudicatorStatus::Suspended,
            verStatus: ProfessionalVerificationStatus::Suspended,
            suspendedAt: now()
        );

        $service = app(JurySelectionService::class);
        $assignment = $service->assignJurorToCase($case);

        $this->assertNull($assignment);
    }

    #[Test]
    public function expired_professional_excluded_from_selection(): void
    {
        $complainant = $this->createUser('comp6@example.com');
        $respondent = $this->createUser('resp6@example.com');
        $case = $this->createCase($complainant, $respondent);

        $expiredLawyer = $this->createUser('expired.lawyer@example.com');
        $this->makeVerifiedAdjudicator(
            $expiredLawyer,
            expiresAt: now()->subDay() // Expired yesterday
        );

        $service = app(JurySelectionService::class);
        $assignment = $service->assignJurorToCase($case);

        $this->assertNull($assignment);
    }

    #[Test]
    public function unassigned_verified_lawyer_cannot_view_arbitrary_case(): void
    {
        $complainant = $this->createUser('comp7@example.com');
        $respondent = $this->createUser('resp7@example.com');
        $case = $this->createCase($complainant, $respondent);

        $unassignedLawyer = $this->createUser('unassigned.lawyer@example.com');
        $this->makeVerifiedAdjudicator($unassignedLawyer);

        $response = $this->withHeaders($this->authHeaders($unassignedLawyer))
            ->getJson("/api/tribunal/cases/{$case->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function accepted_adjudicator_can_view_assigned_case(): void
    {
        $complainant = $this->createUser('comp8@example.com');
        $respondent = $this->createUser('resp8@example.com');
        $case = $this->createCase($complainant, $respondent);

        $adjudicator = $this->createUser('adjudicator.accepted@example.com');
        $this->makeVerifiedAdjudicator($adjudicator);

        TribunalJuryAssignment::create([
            'tribunal_case_id' => $case->id,
            'juror_id' => $adjudicator->id,
            'role' => 'juror',
            'status' => TribunalJuryAssignmentStatus::Accepted,
            'assigned_at' => now(),
            'responded_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders($adjudicator))
            ->getJson("/api/tribunal/cases/{$case->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $case->id);
    }

    #[Test]
    public function private_credential_documents_reject_unauthorized_users(): void
    {
        Storage::fake('local');

        $applicant = $this->createUser('applicant.docs@example.com');
        $unauthorized = $this->createUser('unauth.user@example.com');
        $admin = $this->createUser('admin.docs@example.com', isAdmin: true);

        $fakeDoc = UploadedFile::fake()->create('bar_license.pdf', 200, 'application/pdf');
        $storedPath = $fakeDoc->storeAs("professional_verifications/{$applicant->id}", 'bar_license.pdf', 'local');

        $pv = ProfessionalVerification::create([
            'user_id' => $applicant->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Pending,
            'issuing_authority' => 'Bar Council',
            'qualification_document_path' => $storedPath,
            'submitted_at' => now(),
        ]);

        // Unauthorized normal user -> 403
        $unauthResponse = $this->withHeaders($this->authHeaders($unauthorized))
            ->get("/api/admin/professional-verifications/{$pv->id}/documents/qualification");
        $unauthResponse->assertStatus(403);

        // Admin -> 200 stream
        $adminResponse = $this->withHeaders($this->authHeaders($admin))
            ->get("/api/admin/professional-verifications/{$pv->id}/documents/qualification");
        $adminResponse->assertStatus(200);
    }

    #[Test]
    public function tribunal_me_endpoint_returns_accurate_capabilities(): void
    {
        $user = $this->createUser('cap.user@example.com');
        $this->makeVerifiedAdjudicator($user);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/tribunal/me');

        $response->assertStatus(200);
        $response->assertJsonPath('can_submit_cases', true);
        $response->assertJsonPath('professional_verification.profession_type', 'attorney_at_law');
        $response->assertJsonPath('professional_verification.status', 'verified');
        $response->assertJsonPath('adjudicator.eligible', true);
        $response->assertJsonPath('adjudicator.available', true);
    }
}
