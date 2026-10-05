<?php

namespace Tests\Feature;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalConversationType;
use App\Enums\TribunalJuryAssignmentStatus;
use App\Enums\TribunalPartyRole;
use App\Enums\TribunalRepresentationRequestStatus;
use App\Enums\TribunalRepresentativeAssignmentStatus;
use App\Models\ApiToken;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\TribunalCase;
use App\Models\TribunalConversation;
use App\Models\TribunalJuryAssignment;
use App\Models\TribunalRepresentationRequest;
use App\Models\TribunalRepresentativeAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TribunalBatch4Test extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();

        $cat = \App\Models\Category::create(['name' => 'Legal Services']);
        $prof = \App\Models\profession::create([
            'category_id' => $cat->id,
            'name' => 'Attorney at Law',
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

    protected function makeVerifiedAttorney(User $user, string $barNumber = 'BAR-12345'): ProfessionalVerification
    {
        return ProfessionalVerification::create([
            'user_id' => $user->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'registration_number' => $barNumber,
            'issuing_authority' => 'Supreme Court Bar Association',
            'years_of_experience' => 8,
            'verification_status' => ProfessionalVerificationStatus::Verified,
            'verified_at' => now(),
            'reviewed_at' => now(),
        ]);
    }

    protected function makeVerifiedJudge(User $user): ProfessionalVerification
    {
        return ProfessionalVerification::create([
            'user_id' => $user->id,
            'profession_type' => ProfessionalType::Judge,
            'registration_number' => 'JUDGE-999',
            'issuing_authority' => 'State Judiciary',
            'years_of_experience' => 15,
            'verification_status' => ProfessionalVerificationStatus::Verified,
            'verified_at' => now(),
            'reviewed_at' => now(),
        ]);
    }

    protected function createCase(User $complainant, User $respondent): TribunalCase
    {
        $case = TribunalCase::create([
            'created_by' => $complainant->id,
            'title' => 'Breach of Confidentiality Agreement',
            'category' => 'Intellectual Property',
            'description' => 'Respondent disclosed trade secrets in violation of non-disclosure agreement.',
            'status' => TribunalCaseStatus::Submitted,
            'severity' => 'high',
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

    #[Test]
    public function only_verified_attorneys_appear_in_representatives_directory()
    {
        $lawyer1 = $this->createUser('lawyer1@example.com', 'Alice', 'Lawyer');
        $this->makeVerifiedAttorney($lawyer1, 'BAR-1001');

        $lawyerPending = $this->createUser('lawyer2@example.com', 'Bob', 'Pending');
        ProfessionalVerification::create([
            'user_id' => $lawyerPending->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'registration_number' => 'BAR-1002',
            'issuing_authority' => 'State Bar',
            'years_of_experience' => 5,
            'verification_status' => ProfessionalVerificationStatus::Pending,
        ]);

        $judgeUser = $this->createUser('judge@example.com', 'Honorable', 'Judge');
        $this->makeVerifiedJudge($judgeUser);

        $client = $this->createUser('client@example.com', 'Normal', 'Client');

        $res = $this->withHeaders($this->authHeaders($client))
            ->getJson('/api/tribunal/representatives');

        $res->assertOk()
            ->assertJsonPath('status', true);

        $data = $res->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($lawyer1->id, $data[0]['id']);
    }

    #[Test]
    public function client_can_request_representation_and_conflict_checks_are_enforced()
    {
        $complainant = $this->createUser('complainant@example.com');
        $respondent = $this->createUser('respondent@example.com');
        $case = $this->createCase($complainant, $respondent);

        $attorney = $this->createUser('attorney@example.com');
        $this->makeVerifiedAttorney($attorney);

        $unverifiedUser = $this->createUser('ordinary@example.com');

        // 1. Cannot request unverified user
        $fail1 = $this->withHeaders($this->authHeaders($complainant))
            ->postJson("/api/tribunal/cases/{$case->id}/representation-requests", [
                'representative_user_id' => $unverifiedUser->id,
                'message' => 'Please help me',
            ]);
        $fail1->assertStatus(422);

        // 2. Complainant requests verified attorney successfully
        $success = $this->withHeaders($this->authHeaders($complainant))
            ->postJson("/api/tribunal/cases/{$case->id}/representation-requests", [
                'representative_user_id' => $attorney->id,
                'message' => 'Please represent me in this high-severity breach case.',
            ]);
        $success->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('tribunal_representation_requests', [
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $attorney->id,
            'status' => 'pending',
        ]);

        // 3. Cannot submit duplicate pending request
        $failDup = $this->withHeaders($this->authHeaders($complainant))
            ->postJson("/api/tribunal/cases/{$case->id}/representation-requests", [
                'representative_user_id' => $attorney->id,
                'message' => 'Duplicate request',
            ]);
        $failDup->assertStatus(422);
    }

    #[Test]
    public function attorney_cannot_represent_both_parties_in_same_case()
    {
        $complainant = $this->createUser('complainant@example.com');
        $respondent = $this->createUser('respondent@example.com');
        $case = $this->createCase($complainant, $respondent);

        $attorney = $this->createUser('attorney@example.com');
        $this->makeVerifiedAttorney($attorney);

        // Complainant requests attorney and attorney is assigned
        $req = TribunalRepresentationRequest::create([
            'tribunal_case_id' => $case->id,
            'requested_by' => $complainant->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $attorney->id,
            'side' => TribunalPartyRole::Complainant,
            'status' => TribunalRepresentationRequestStatus::Accepted,
            'requested_at' => now(),
            'responded_at' => now(),
        ]);

        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $attorney->id,
            'representation_request_id' => $req->id,
            'side' => TribunalPartyRole::Complainant,
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'accepted_at' => now(),
        ]);

        // Respondent attempts to request the same attorney -> Conflict 422
        $res = $this->withHeaders($this->authHeaders($respondent))
            ->postJson("/api/tribunal/cases/{$case->id}/representation-requests", [
                'representative_user_id' => $attorney->id,
                'message' => 'Represent me too please',
            ]);

        $res->assertStatus(422);
    }

    #[Test]
    public function attorney_cannot_represent_if_already_adjudicator_in_same_case()
    {
        $complainant = $this->createUser('complainant@example.com');
        $respondent = $this->createUser('respondent@example.com');
        $case = $this->createCase($complainant, $respondent);

        $attorney = $this->createUser('attorney@example.com');
        $this->makeVerifiedAttorney($attorney);

        // Assign attorney as adjudicator
        TribunalJuryAssignment::create([
            'tribunal_case_id' => $case->id,
            'juror_id' => $attorney->id,
            'status' => TribunalJuryAssignmentStatus::Accepted,
            'assigned_at' => now(),
            'invited_at' => now(),
            'responded_at' => now(),
        ]);

        // Complainant tries to request this attorney -> Conflict 422
        $res = $this->withHeaders($this->authHeaders($complainant))
            ->postJson("/api/tribunal/cases/{$case->id}/representation-requests", [
                'representative_user_id' => $attorney->id,
                'message' => 'Please represent me',
            ]);

        $res->assertStatus(422);
    }

    #[Test]
    public function attorney_can_view_inbox_and_accept_request_creating_assignment_and_conversation()
    {
        $complainant = $this->createUser('complainant@example.com');
        $respondent = $this->createUser('respondent@example.com');
        $case = $this->createCase($complainant, $respondent);

        $attorney = $this->createUser('attorney@example.com');
        $this->makeVerifiedAttorney($attorney);

        // Complainant sends request
        $this->withHeaders($this->authHeaders($complainant))
            ->postJson("/api/tribunal/cases/{$case->id}/representation-requests", [
                'representative_user_id' => $attorney->id,
                'message' => 'Need legal representation.',
            ]);

        // Attorney checks inbox
        $inboxRes = $this->withHeaders($this->authHeaders($attorney))
            ->getJson('/api/tribunal/representation-requests');

        $inboxRes->assertOk();
        $requests = $inboxRes->json('data');
        $this->assertCount(1, $requests);
        $requestId = $requests[0]['id'];

        // Attorney accepts request
        $acceptRes = $this->withHeaders($this->authHeaders($attorney))
            ->postJson("/api/tribunal/representation-requests/{$requestId}/accept", [
                'response_note' => 'I am pleased to represent you in this matter.',
            ]);

        $acceptRes->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('tribunal_representation_requests', [
            'id' => $requestId,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('tribunal_representative_assignments', [
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $attorney->id,
            'status' => 'active',
        ]);

        // Verify conversation was created
        $this->assertDatabaseHas('tribunal_conversations', [
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $attorney->id,
            'active' => 1,
        ]);
    }

    #[Test]
    public function attorney_can_decline_request_with_reason()
    {
        $complainant = $this->createUser('complainant@example.com');
        $respondent = $this->createUser('respondent@example.com');
        $case = $this->createCase($complainant, $respondent);

        $attorney = $this->createUser('attorney@example.com');
        $this->makeVerifiedAttorney($attorney);

        $request = TribunalRepresentationRequest::create([
            'tribunal_case_id' => $case->id,
            'requested_by' => $complainant->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $attorney->id,
            'side' => TribunalPartyRole::Complainant,
            'status' => TribunalRepresentationRequestStatus::Pending,
            'requested_at' => now(),
        ]);

        $declineRes = $this->withHeaders($this->authHeaders($attorney))
            ->postJson("/api/tribunal/representation-requests/{$request->id}/decline", [
                'reason' => 'I currently have no bandwidth due to trial schedule.',
            ]);

        $declineRes->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('tribunal_representation_requests', [
            'id' => $request->id,
            'status' => 'declined',
            'decline_reason' => 'I currently have no bandwidth due to trial schedule.',
        ]);

        $this->assertDatabaseMissing('tribunal_representative_assignments', [
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
        ]);
    }

    #[Test]
    public function active_representative_can_view_case_evidence_and_upload_evidence()
    {
        Storage::fake('private');

        $complainant = $this->createUser('complainant@example.com');
        $respondent = $this->createUser('respondent@example.com');
        $case = $this->createCase($complainant, $respondent);

        $attorney = $this->createUser('attorney@example.com');
        $this->makeVerifiedAttorney($attorney);

        // Assign attorney
        $assignment = TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $attorney->id,
            'side' => TribunalPartyRole::Complainant,
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'accepted_at' => now(),
        ]);

        // 1. Attorney can view case details
        $viewCase = $this->withHeaders($this->authHeaders($attorney))
            ->getJson("/api/tribunal/cases/{$case->id}");
        $viewCase->assertOk()
            ->assertJsonPath('data.id', $case->id);

        // 2. Attorney can view evidence list
        $viewEvidence = $this->withHeaders($this->authHeaders($attorney))
            ->getJson("/api/tribunal/cases/{$case->id}/evidence");
        $viewEvidence->assertOk();

        // 3. Attorney can upload evidence on client's behalf
        $file = UploadedFile::fake()->create('contract_signed.pdf', 500, 'application/pdf');
        $uploadRes = $this->withHeaders($this->authHeaders($attorney))
            ->postJson("/api/tribunal/cases/{$case->id}/evidence", [
                'title' => 'Executed Non-Disclosure Agreement',
                'description' => 'Original signed NDA document',
                'type' => 'document',
                'file' => $file,
            ]);

        $uploadRes->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', 'Executed Non-Disclosure Agreement');

        $this->assertDatabaseHas('tribunal_evidence', [
            'tribunal_case_id' => $case->id,
            'uploaded_by' => $attorney->id,
            'title' => 'Executed Non-Disclosure Agreement',
        ]);
    }

    #[Test]
    public function confidential_communication_is_strictly_restricted_to_client_and_representative()
    {
        $complainant = $this->createUser('complainant@example.com');
        $respondent = $this->createUser('respondent@example.com');
        $case = $this->createCase($complainant, $respondent);

        $attorney = $this->createUser('attorney@example.com');
        $this->makeVerifiedAttorney($attorney);

        // Assign attorney to complainant
        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $attorney->id,
            'side' => TribunalPartyRole::Complainant,
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'accepted_at' => now(),
        ]);

        $conversation = TribunalConversation::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $attorney->id,
            'type' => TribunalConversationType::ComplainantRepresentative,
            'active' => true,
        ]);

        // 1. Client sends message
        $msg1 = $this->withHeaders($this->authHeaders($complainant))
            ->postJson("/api/tribunal/conversations/{$conversation->id}/messages", [
                'body' => 'Hello Counselor, I have gathered the emails.',
            ]);
        $msg1->assertStatus(201)
            ->assertJsonPath('data.body', 'Hello Counselor, I have gathered the emails.');

        // 2. Attorney sends message
        $msg2 = $this->withHeaders($this->authHeaders($attorney))
            ->postJson("/api/tribunal/conversations/{$conversation->id}/messages", [
                'body' => 'Excellent, please forward them immediately.',
            ]);
        $msg2->assertStatus(201);

        // 3. Opposing party (respondent) attempts to read conversation messages -> 403 Forbidden
        $opposingRead = $this->withHeaders($this->authHeaders($respondent))
            ->getJson("/api/tribunal/conversations/{$conversation->id}/messages");
        $opposingRead->assertStatus(403);

        // 4. Opposing party attempts to post message -> 403 Forbidden
        $opposingPost = $this->withHeaders($this->authHeaders($respondent))
            ->postJson("/api/tribunal/conversations/{$conversation->id}/messages", [
                'body' => 'I am intruding on your private chat',
            ]);
        $opposingPost->assertStatus(403);

        // 5. Adjudicator / Third party attempts to read -> 403 Forbidden
        $stranger = $this->createUser('stranger@example.com');
        $strangerRead = $this->withHeaders($this->authHeaders($stranger))
            ->getJson("/api/tribunal/conversations/{$conversation->id}/messages");
        $strangerRead->assertStatus(403);
    }

    #[Test]
    public function either_client_or_representative_can_end_representation()
    {
        $complainant = $this->createUser('complainant@example.com');
        $respondent = $this->createUser('respondent@example.com');
        $case = $this->createCase($complainant, $respondent);

        $attorney = $this->createUser('attorney@example.com');
        $this->makeVerifiedAttorney($attorney);

        $assignment = TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $attorney->id,
            'side' => TribunalPartyRole::Complainant,
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'accepted_at' => now(),
        ]);

        $conversation = TribunalConversation::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $attorney->id,
            'type' => TribunalConversationType::ComplainantRepresentative,
            'active' => true,
        ]);

        // Client ends representation
        $endRes = $this->withHeaders($this->authHeaders($complainant))
            ->postJson("/api/tribunal/cases/{$case->id}/representation/end", [
                'reason' => 'Mutual agreement to conclude representation.',
            ]);

        $endRes->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('tribunal_representative_assignments', [
            'id' => $assignment->id,
            'status' => 'ended',
            'ended_by' => $complainant->id,
        ]);

        $this->assertDatabaseHas('tribunal_conversations', [
            'id' => $conversation->id,
            'active' => 0,
        ]);

        // Former representative no longer has case access
        $caseAccess = $this->withHeaders($this->authHeaders($attorney))
            ->getJson("/api/tribunal/cases/{$case->id}");
        $caseAccess->assertStatus(403);
    }

    #[Test]
    public function capability_endpoint_reports_representative_status()
    {
        $attorney = $this->createUser('attorney@example.com');
        $this->makeVerifiedAttorney($attorney);

        $res = $this->withHeaders($this->authHeaders($attorney))
            ->getJson('/api/tribunal/me');

        $res->assertOk()
            ->assertJsonPath('representative.eligible', true)
            ->assertJsonPath('representative.pending_requests', 0)
            ->assertJsonPath('representative.active_cases', 0);

        $ordinary = $this->createUser('ordinary@example.com');
        $resOrdinary = $this->withHeaders($this->authHeaders($ordinary))
            ->getJson('/api/tribunal/me');

        $resOrdinary->assertOk()
            ->assertJsonPath('representative.eligible', false);
    }
}
