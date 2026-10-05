<?php

namespace Tests\Feature;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalAdjudicatorStatus;
use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalJurorEligibilityStatus;
use App\Enums\TribunalJuryAssignmentStatus;
use App\Enums\TribunalPartyRole;
use App\Enums\TribunalQualificationStatus;
use App\Models\ApiToken;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\TribunalAdjudicatorProfile;
use App\Models\TribunalCase;
use App\Models\TribunalEvidence;
use App\Models\TribunalJurorProfile;
use App\Models\TribunalJuryAssignment;
use App\Models\User;
use App\Services\Tribunal\JurySelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TribunalBatch2Test extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();

        $cat = \App\Models\Category::create(['name' => 'General Category']);
        $prof = \App\Models\profession::create([
            'category_id' => $cat->id,
            'name' => 'General Profession',
        ]);
        $this->professionId = $prof->id;
    }

    protected function createUser(string $email, ?string $firstName = null, ?string $lastName = null): User
    {
        $suffix = Str::lower(Str::random(6));
        $firstName = $firstName ? "{$firstName}_{$suffix}" : "User_{$suffix}";
        $lastName = $lastName ? "{$lastName}_{$suffix}" : "Test_{$suffix}";

        $user = User::create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        Profile::create([
            'user_id' => $user->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 1,
            'profession_id' => $this->professionId,
            'birth_date' => '2000-01-01',
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
            'title' => 'Contractual Non-performance',
            'category' => 'Financial Dispute',
            'description' => 'Failure to deliver services according to contract terms.',
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

    protected function makeAdjudicator(User $user, bool $eligible = true, bool $available = true, bool $suspended = false): void
    {
        $pv = ProfessionalVerification::create([
            'user_id' => $user->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => $suspended ? ProfessionalVerificationStatus::Suspended : ($eligible ? ProfessionalVerificationStatus::Verified : ProfessionalVerificationStatus::Rejected),
            'issuing_authority' => 'Bar Association',
            'years_of_experience' => 5,
            'verified_at' => now(),
        ]);

        TribunalAdjudicatorProfile::create([
            'user_id' => $user->id,
            'professional_verification_id' => $pv->id,
            'status' => $suspended ? TribunalAdjudicatorStatus::Suspended : ($eligible ? TribunalAdjudicatorStatus::Eligible : TribunalAdjudicatorStatus::Pending),
            'qualification_status' => $eligible ? TribunalQualificationStatus::Passed : TribunalQualificationStatus::NotStarted,
            'available' => $available,
            'suspended_at' => $suspended ? now() : null,
        ]);

        TribunalJurorProfile::create([
            'user_id' => $user->id,
            'status' => $eligible ? TribunalJurorEligibilityStatus::Eligible : TribunalJurorEligibilityStatus::Suspended,
            'available' => $available,
        ]);
    }

    #[Test]
    public function participant_can_upload_evidence(): void
    {
        Storage::fake('local');

        $complainant = $this->createUser('complainant@example.com', 'Alice', 'Smith');
        $respondent = $this->createUser('respondent@example.com', 'Bob', 'Jones');
        $case = $this->createCase($complainant, $respondent);

        $file = UploadedFile::fake()->create('agreement.pdf', 150, 'application/pdf');

        $response = $this->withHeaders($this->authHeaders($complainant))
            ->postJson("/api/tribunal/cases/{$case->id}/evidence", [
                'type' => 'document',
                'title' => 'Signed Service Agreement',
                'description' => 'Original signed PDF copy of the contract.',
                'file' => $file,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.evidence_number', 'EV-0001');
        $response->assertJsonPath('data.title', 'Signed Service Agreement');

        $this->assertDatabaseHas('tribunal_evidence', [
            'tribunal_case_id' => $case->id,
            'evidence_number' => 'EV-0001',
            'uploaded_by' => $complainant->id,
            'title' => 'Signed Service Agreement',
        ]);

        // Event logged
        $this->assertDatabaseHas('tribunal_case_events', [
            'tribunal_case_id' => $case->id,
            'event_type' => 'evidence_uploaded',
        ]);
    }

    #[Test]
    public function unrelated_user_cannot_upload_or_view_evidence(): void
    {
        Storage::fake('local');

        $complainant = $this->createUser('alice@example.com');
        $respondent = $this->createUser('bob@example.com');
        $stranger = $this->createUser('charlie@example.com');
        $case = $this->createCase($complainant, $respondent);

        // Upload by stranger rejected with 403
        $responseUpload = $this->withHeaders($this->authHeaders($stranger))
            ->postJson("/api/tribunal/cases/{$case->id}/evidence", [
                'type' => 'statement',
                'title' => 'Stranger Testimony',
                'description' => 'I am an unrelated third party.',
            ]);

        $responseUpload->assertStatus(403);

        // View evidence list by stranger rejected with 403
        $responseView = $this->withHeaders($this->authHeaders($stranger))
            ->getJson("/api/tribunal/cases/{$case->id}/evidence");

        $responseView->assertStatus(403);
    }

    #[Test]
    public function opponent_can_challenge_evidence(): void
    {
        Storage::fake('local');

        $complainant = $this->createUser('c1@example.com');
        $respondent = $this->createUser('r1@example.com');
        $case = $this->createCase($complainant, $respondent);

        $evidence = TribunalEvidence::create([
            'tribunal_case_id' => $case->id,
            'uploaded_by' => $complainant->id,
            'evidence_number' => 'EV-0001',
            'type' => 'statement',
            'title' => 'Complainant Written Statement',
            'description' => 'The respondent did not deliver on time.',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Respondent challenges complainant's evidence
        $response = $this->withHeaders($this->authHeaders($respondent))
            ->postJson("/api/tribunal/cases/{$case->id}/evidence/{$evidence->id}/challenge", [
                'reason' => 'This statement omits the approved 30-day extension agreement.',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('tribunal_evidence_challenges', [
            'tribunal_evidence_id' => $evidence->id,
            'challenged_by' => $respondent->id,
            'status' => 'pending',
        ]);

        $this->assertEquals('challenged', $evidence->fresh()->status->value);

        // Event logged
        $this->assertDatabaseHas('tribunal_case_events', [
            'tribunal_case_id' => $case->id,
            'event_type' => 'evidence_challenged',
        ]);
    }

    #[Test]
    public function uploader_cannot_challenge_own_evidence(): void
    {
        $complainant = $this->createUser('c2@example.com');
        $respondent = $this->createUser('r2@example.com');
        $case = $this->createCase($complainant, $respondent);

        $evidence = TribunalEvidence::create([
            'tribunal_case_id' => $case->id,
            'uploaded_by' => $complainant->id,
            'evidence_number' => 'EV-0001',
            'type' => 'statement',
            'title' => 'My Statement',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Complainant challenges own evidence
        $response = $this->withHeaders($this->authHeaders($complainant))
            ->postJson("/api/tribunal/cases/{$case->id}/evidence/{$evidence->id}/challenge", [
                'reason' => 'I made a mistake in this statement.',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function eligible_juror_selection_excludes_case_parties(): void
    {
        $complainant = $this->createUser('c3@example.com');
        $respondent = $this->createUser('r3@example.com');
        $case = $this->createCase($complainant, $respondent);

        // Make complainant and respondent eligible adjudicators as well
        $this->makeAdjudicator($complainant, true, true);
        $this->makeAdjudicator($respondent, true, true);

        // Create a neutral eligible adjudicator
        $neutralJuror = $this->createUser('neutral_juror@example.com');
        $this->makeAdjudicator($neutralJuror, true, true);

        $service = app(JurySelectionService::class);
        $assignment = $service->assignJurorToCase($case);

        $this->assertNotNull($assignment);
        $this->assertEquals($neutralJuror->id, $assignment->juror_id);
        $this->assertNotEquals($complainant->id, $assignment->juror_id);
        $this->assertNotEquals($respondent->id, $assignment->juror_id);
    }

    #[Test]
    public function ineligible_or_unavailable_juror_is_never_selected(): void
    {
        $complainant = $this->createUser('c4@example.com');
        $respondent = $this->createUser('r4@example.com');
        $case = $this->createCase($complainant, $respondent);

        // Suspended juror
        $suspendedUser = $this->createUser('suspended@example.com');
        TribunalJurorProfile::create([
            'user_id' => $suspendedUser->id,
            'status' => TribunalJurorEligibilityStatus::Suspended,
            'available' => true,
        ]);

        // Unavailable juror
        $unavailableUser = $this->createUser('unavailable@example.com');
        TribunalJurorProfile::create([
            'user_id' => $unavailableUser->id,
            'status' => TribunalJurorEligibilityStatus::Eligible,
            'available' => false,
        ]);

        $service = app(JurySelectionService::class);
        $assignment = $service->assignJurorToCase($case);

        // No eligible jurors -> returns null, does not assign suspended or unavailable
        $this->assertNull($assignment);
        $this->assertEquals(TribunalCaseStatus::JurySelection, $case->fresh()->status);
    }

    #[Test]
    public function juror_can_declare_conflict_and_trigger_recusal_and_replacement(): void
    {
        $complainant = $this->createUser('c5@example.com');
        $respondent = $this->createUser('r5@example.com');
        $case = $this->createCase($complainant, $respondent);

        $firstJuror = $this->createUser('first_juror@example.com');
        $this->makeAdjudicator($firstJuror, true, true);

        $service = app(JurySelectionService::class);
        $assignment = $service->assignJurorToCase($case);
        $this->assertEquals($firstJuror->id, $assignment->juror_id);

        // Now create the replacement juror candidate
        $replacementJuror = $this->createUser('replacement_juror@example.com');
        $this->makeAdjudicator($replacementJuror, true, true);

        // First juror declares conflict
        $response = $this->withHeaders($this->authHeaders($firstJuror))
            ->postJson("/api/tribunal/cases/{$case->id}/jury/conflict", [
                'has_conflict' => true,
                'conflict_reason' => 'I have a close personal friendship with the respondent.',
            ]);

        $response->assertStatus(200);

        // First assignment becomes recused
        $this->assertDatabaseHas('tribunal_jury_assignments', [
            'id' => $assignment->id,
            'juror_id' => $firstJuror->id,
            'status' => TribunalJuryAssignmentStatus::Recused->value,
        ]);

        // Conflict recorded
        $this->assertDatabaseHas('tribunal_juror_conflicts', [
            'tribunal_jury_assignment_id' => $assignment->id,
            'juror_id' => $firstJuror->id,
            'has_conflict' => true,
        ]);

        // Replacement automatically selected
        $this->assertDatabaseHas('tribunal_jury_assignments', [
            'tribunal_case_id' => $case->id,
            'juror_id' => $replacementJuror->id,
            'status' => TribunalJuryAssignmentStatus::Invited->value,
        ]);
    }

    #[Test]
    public function accepted_juror_can_view_case_evidence_while_unrelated_juror_cannot(): void
    {
        Storage::fake('local');

        $complainant = $this->createUser('c6@example.com');
        $respondent = $this->createUser('r6@example.com');
        $case = $this->createCase($complainant, $respondent);

        $acceptedJuror = $this->createUser('accepted_juror@example.com');
        $unrelatedJuror = $this->createUser('other_juror@example.com');

        TribunalJuryAssignment::create([
            'tribunal_case_id' => $case->id,
            'juror_id' => $acceptedJuror->id,
            'role' => 'juror',
            'status' => TribunalJuryAssignmentStatus::Accepted,
            'assigned_at' => now(),
            'responded_at' => now(),
        ]);

        TribunalEvidence::create([
            'tribunal_case_id' => $case->id,
            'uploaded_by' => $complainant->id,
            'evidence_number' => 'EV-0001',
            'type' => 'statement',
            'title' => 'Secret Evidence Statement',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Accepted juror CAN view case
        $responseAccepted = $this->withHeaders($this->authHeaders($acceptedJuror))
            ->getJson("/api/tribunal/cases/{$case->id}");
        $responseAccepted->assertStatus(200);

        // Accepted juror CAN view evidence
        $responseEvidence = $this->withHeaders($this->authHeaders($acceptedJuror))
            ->getJson("/api/tribunal/cases/{$case->id}/evidence");
        $responseEvidence->assertStatus(200);
        $responseEvidence->assertJsonCount(1, 'data');

        // Unrelated juror CANNOT view case
        $responseUnrelated = $this->withHeaders($this->authHeaders($unrelatedJuror))
            ->getJson("/api/tribunal/cases/{$case->id}");
        $responseUnrelated->assertStatus(403);

        // Unrelated juror CANNOT view evidence
        $responseUnrelatedEvidence = $this->withHeaders($this->authHeaders($unrelatedJuror))
            ->getJson("/api/tribunal/cases/{$case->id}/evidence");
        $responseUnrelatedEvidence->assertStatus(403);
    }

    #[Test]
    public function private_file_endpoint_returns_403_for_unauthorized_user(): void
    {
        Storage::fake('local');

        $complainant = $this->createUser('c7@example.com');
        $respondent = $this->createUser('r7@example.com');
        $unauthorized = $this->createUser('unauthorized@example.com');
        $case = $this->createCase($complainant, $respondent);

        $fakeFile = UploadedFile::fake()->create('confidential.pdf', 100, 'application/pdf');
        $storedPath = $fakeFile->storeAs("tribunal_evidence/{$case->id}", 'uuid.pdf', 'local');

        $evidence = TribunalEvidence::create([
            'tribunal_case_id' => $case->id,
            'uploaded_by' => $complainant->id,
            'evidence_number' => 'EV-0001',
            'type' => 'document',
            'title' => 'Confidential Financial Report',
            'original_filename' => 'confidential.pdf',
            'stored_filename' => 'uuid.pdf',
            'file_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'file_size' => 102400,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Unauthorized user attempts download -> 403
        $responseUnauthorized = $this->withHeaders($this->authHeaders($unauthorized))
            ->getJson("/api/tribunal/cases/{$case->id}/evidence/{$evidence->id}/download");

        $responseUnauthorized->assertStatus(403);

        // Complainant can download -> 200
        $responseComplainant = $this->withHeaders($this->authHeaders($complainant))
            ->get("/api/tribunal/cases/{$case->id}/evidence/{$evidence->id}/download");

        $responseComplainant->assertStatus(200);
    }
}
