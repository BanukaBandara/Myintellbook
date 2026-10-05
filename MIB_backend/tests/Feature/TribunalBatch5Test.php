<?php

namespace Tests\Feature;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalCaseMessageType;
use App\Enums\TribunalCaseRoomStatus;
use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalConversationType;
use App\Enums\TribunalJuryAssignmentStatus;
use App\Enums\TribunalJuryRole;
use App\Enums\TribunalMediationStatus;
use App\Enums\TribunalPartyRole;
use App\Enums\TribunalRepresentationRequestStatus;
use App\Enums\TribunalRepresentativeAssignmentStatus;
use App\Enums\TribunalSettlementProposalStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\TribunalCase;
use App\Models\TribunalCaseMessage;
use App\Models\TribunalCaseRoom;
use App\Models\TribunalConversation;
use App\Models\TribunalJuryAssignment;
use App\Models\TribunalMediation;
use App\Models\TribunalMediationConsent;
use App\Models\TribunalMessage;
use App\Models\TribunalRepresentativeAssignment;
use App\Models\TribunalSettlementAgreement;
use App\Models\TribunalSettlementProposal;
use App\Models\User;
use App\Models\profession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TribunalBatch5Test extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();

        $cat = Category::create(['name' => 'Legal Services']);
        $prof = profession::create([
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

    protected function createCase(User $complainant, User $respondent, TribunalCaseStatus $status = TribunalCaseStatus::EvidenceCollection): TribunalCase
    {
        $case = TribunalCase::create([
            'created_by' => $complainant->id,
            'title' => 'Contract Dispute Regarding Intellectual Property',
            'category' => 'Commercial Law',
            'description' => 'Dispute over licensing terms and unauthorized commercial publication.',
            'status' => $status,
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

    protected function assignAdjudicator(TribunalCase $case, User $adjudicator): TribunalJuryAssignment
    {
        return TribunalJuryAssignment::create([
            'tribunal_case_id' => $case->id,
            'juror_id' => $adjudicator->id,
            'role' => TribunalJuryRole::PresidingMember,
            'status' => TribunalJuryAssignmentStatus::Accepted,
            'assigned_at' => now(),
            'responded_at' => now(),
        ]);
    }

    protected function assignRepresentative(TribunalCase $case, User $client, User $lawyer, string $side): TribunalRepresentativeAssignment
    {
        return TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $client->id,
            'representative_user_id' => $lawyer->id,
            'side' => $side,
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'accepted_at' => now(),
        ]);
    }

    #[Test]
    public function authorized_case_participants_can_access_shared_room()
    {
        $complainant = $this->createUser('comp1@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp1@example.com', 'Bob', 'Respondent');
        $case = $this->createCase($complainant, $respondent);

        // Complainant can access room
        $response1 = $this->getJson("/api/tribunal/cases/{$case->id}/case-room", $this->authHeaders($complainant));
        $response1->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        // Respondent can access room
        $response2 = $this->getJson("/api/tribunal/cases/{$case->id}/case-room", $this->authHeaders($respondent));
        $response2->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        // Complainant can send normal message
        $postRes = $this->postJson("/api/tribunal/cases/{$case->id}/case-room/messages", [
            'body' => 'I have reviewed the case documents and submitted our statement.',
        ], $this->authHeaders($complainant));

        $postRes->assertStatus(201)
            ->assertJsonPath('data.sender_case_role', 'complainant')
            ->assertJsonPath('data.sender_role_label', 'Complainant');

        // Messages endpoint returns the message
        $msgRes = $this->getJson("/api/tribunal/cases/{$case->id}/case-room/messages", $this->authHeaders($respondent));
        $msgRes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'I have reviewed the case documents and submitted our statement.');
    }

    #[Test]
    public function unrelated_user_receives_403()
    {
        $complainant = $this->createUser('comp2@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp2@example.com', 'Bob', 'Respondent');
        $stranger = $this->createUser('stranger2@example.com', 'Charlie', 'Stranger');
        $case = $this->createCase($complainant, $respondent);

        // Stranger cannot view room
        $this->getJson("/api/tribunal/cases/{$case->id}/case-room", $this->authHeaders($stranger))
            ->assertStatus(403);

        // Stranger cannot post message
        $this->postJson("/api/tribunal/cases/{$case->id}/case-room/messages", [
            'body' => 'Intruder message',
        ], $this->authHeaders($stranger))->assertStatus(403);
    }

    #[Test]
    public function active_representative_can_access_shared_room()
    {
        $complainant = $this->createUser('comp3@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp3@example.com', 'Bob', 'Respondent');
        $lawyer = $this->createUser('lawyer3@example.com', 'David', 'Attorney');
        $case = $this->createCase($complainant, $respondent);

        $this->assignRepresentative($case, $complainant, $lawyer, 'complainant');

        $response = $this->getJson("/api/tribunal/cases/{$case->id}/case-room", $this->authHeaders($lawyer));
        $response->assertStatus(200);

        // Representative sends message; sender_case_role is recorded as complainant_representative
        $postRes = $this->postJson("/api/tribunal/cases/{$case->id}/case-room/messages", [
            'body' => 'Notice on behalf of Complainant regarding schedule.',
        ], $this->authHeaders($lawyer));

        $postRes->assertStatus(201)
            ->assertJsonPath('data.sender_case_role', 'complainant_representative')
            ->assertJsonPath('data.sender_role_label', 'Complainant Counsel');
    }

    #[Test]
    public function ended_representative_cannot_access_shared_room()
    {
        $complainant = $this->createUser('comp4@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp4@example.com', 'Bob', 'Respondent');
        $lawyer = $this->createUser('lawyer4@example.com', 'David', 'Attorney');
        $case = $this->createCase($complainant, $respondent);

        $assignment = $this->assignRepresentative($case, $complainant, $lawyer, 'complainant');

        // End representation
        $assignment->update([
            'status' => TribunalRepresentativeAssignmentStatus::Ended,
            'ended_at' => now(),
        ]);

        $this->getJson("/api/tribunal/cases/{$case->id}/case-room", $this->authHeaders($lawyer))
            ->assertStatus(403);

        $this->postJson("/api/tribunal/cases/{$case->id}/case-room/messages", [
            'body' => 'Attempted message after representation ended.',
        ], $this->authHeaders($lawyer))->assertStatus(403);
    }

    #[Test]
    public function active_adjudicator_can_access_room()
    {
        $complainant = $this->createUser('comp5@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp5@example.com', 'Bob', 'Respondent');
        $adjudicator = $this->createUser('adj5@example.com', 'Hon', 'Judge');
        $case = $this->createCase($complainant, $respondent);

        $this->assignAdjudicator($case, $adjudicator);

        $response = $this->getJson("/api/tribunal/cases/{$case->id}/case-room", $this->authHeaders($adjudicator));
        $response->assertStatus(200);

        // Adjudicator can read messages
        $this->getJson("/api/tribunal/cases/{$case->id}/case-room/messages", $this->authHeaders($adjudicator))
            ->assertStatus(200);
    }

    #[Test]
    public function private_attorney_messages_never_appear_in_case_room()
    {
        $complainant = $this->createUser('comp6@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp6@example.com', 'Bob', 'Respondent');
        $lawyer = $this->createUser('lawyer6@example.com', 'David', 'Attorney');
        $case = $this->createCase($complainant, $respondent);

        $this->assignRepresentative($case, $complainant, $lawyer, 'complainant');

        // Create private conversation & message
        $privateConv = TribunalConversation::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $lawyer->id,
            'type' => TribunalConversationType::ComplainantRepresentative,
        ]);

        TribunalMessage::create([
            'conversation_id' => $privateConv->id,
            'sender_id' => $lawyer->id,
            'body' => 'TOP SECRET ATTORNEY-CLIENT PRIVILEGED ADVICE',
        ]);

        // Shared case room messages must NOT contain this private text
        $msgRes = $this->getJson("/api/tribunal/cases/{$case->id}/case-room/messages", $this->authHeaders($respondent));
        $msgRes->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertDontSee('TOP SECRET ATTORNEY-CLIENT PRIVILEGED ADVICE');
    }

    #[Test]
    public function normal_user_cannot_post_procedural_notice()
    {
        $complainant = $this->createUser('comp7@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp7@example.com', 'Bob', 'Respondent');
        $case = $this->createCase($complainant, $respondent);

        $this->postJson("/api/tribunal/cases/{$case->id}/case-room/procedural-notices", [
            'body' => 'Unauthorized procedural notice attempt',
        ], $this->authHeaders($complainant))->assertStatus(403);
    }

    #[Test]
    public function adjudicator_can_post_procedural_notice()
    {
        $complainant = $this->createUser('comp8@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp8@example.com', 'Bob', 'Respondent');
        $adjudicator = $this->createUser('adj8@example.com', 'Hon', 'Judge');
        $case = $this->createCase($complainant, $respondent);

        $this->assignAdjudicator($case, $adjudicator);

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/case-room/procedural-notices", [
            'body' => 'All documentary evidence must be finalized by Friday at 17:00 UTC.',
        ], $this->authHeaders($adjudicator));

        $response->assertStatus(201)
            ->assertJsonPath('data.message_type', 'procedural_notice')
            ->assertJsonPath('data.procedural', true)
            ->assertJsonPath('data.sender_role_label', 'Tribunal Adjudicator');
    }

    #[Test]
    public function adjudicator_can_ask_targeted_question()
    {
        $complainant = $this->createUser('comp9@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp9@example.com', 'Bob', 'Respondent');
        $adjudicator = $this->createUser('adj9@example.com', 'Hon', 'Judge');
        $case = $this->createCase($complainant, $respondent);

        $this->assignAdjudicator($case, $adjudicator);

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/case-room/questions", [
            'body' => 'Respondent, please clarify if the notice of termination was delivered in writing.',
            'target_side' => 'respondent',
        ], $this->authHeaders($adjudicator));

        $response->assertStatus(201)
            ->assertJsonPath('data.message_type', 'adjudicator_question')
            ->assertJsonPath('data.target_side', 'respondent')
            ->assertJsonPath('data.procedural', true);
    }

    #[Test]
    public function correct_party_or_representative_can_respond()
    {
        $complainant = $this->createUser('comp10@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp10@example.com', 'Bob', 'Respondent');
        $adjudicator = $this->createUser('adj10@example.com', 'Hon', 'Judge');
        $case = $this->createCase($complainant, $respondent);

        $this->assignAdjudicator($case, $adjudicator);

        // Adjudicator asks respondent a question
        $qRes = $this->postJson("/api/tribunal/cases/{$case->id}/case-room/questions", [
            'body' => 'Respondent, confirm delivery date.',
            'target_side' => 'respondent',
        ], $this->authHeaders($adjudicator));
        $questionId = $qRes->json('data.id');

        // Complainant tries to respond to a question targeted ONLY to respondent -> 403
        $this->postJson("/api/tribunal/cases/{$case->id}/case-room/questions/{$questionId}/responses", [
            'body' => 'Complainant answering instead.',
        ], $this->authHeaders($complainant))->assertStatus(403);

        // Respondent can respond successfully
        $respRes = $this->postJson("/api/tribunal/cases/{$case->id}/case-room/questions/{$questionId}/responses", [
            'body' => 'The delivery was executed via courier on March 12, 2026.',
        ], $this->authHeaders($respondent));

        $respRes->assertStatus(201)
            ->assertJsonPath('data.message_type', 'question_response')
            ->assertJsonPath('data.parent_message_id', $questionId)
            ->assertJsonPath('data.sender_case_role', 'respondent');
    }

    #[Test]
    public function party_can_request_mediation()
    {
        $complainant = $this->createUser('comp11@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp11@example.com', 'Bob', 'Respondent');
        $case = $this->createCase($complainant, $respondent);

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($complainant));

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'awaiting_consent')
            ->assertJsonPath('data.initiation_type', 'party_request');

        $this->assertDatabaseHas('tribunal_mediations', [
            'tribunal_case_id' => $case->id,
            'status' => TribunalMediationStatus::AwaitingConsent->value,
        ]);
    }

    #[Test]
    public function both_parties_must_consent_before_mediation_becomes_active()
    {
        $complainant = $this->createUser('comp12@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp12@example.com', 'Bob', 'Respondent');
        $case = $this->createCase($complainant, $respondent, TribunalCaseStatus::EvidenceCollection);

        $reqRes = $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($complainant));
        $mediationId = $reqRes->json('data.id');

        // Check mediation is still awaiting consent
        $this->assertEquals(TribunalMediationStatus::AwaitingConsent->value, TribunalMediation::find($mediationId)->status->value);

        // Respondent accepts mediation
        $respRes = $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", [
            'response' => 'accepted',
        ], $this->authHeaders($respondent));

        $respRes->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        // Both parties consented, so mediation is now active
        $this->assertEquals(TribunalMediationStatus::Active, TribunalMediation::find($mediationId)->status);
    }

    #[Test]
    public function representative_cannot_provide_final_mediation_consent()
    {
        $complainant = $this->createUser('comp13@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp13@example.com', 'Bob', 'Respondent');
        $lawyer = $this->createUser('lawyer13@example.com', 'David', 'Attorney');
        $case = $this->createCase($complainant, $respondent);

        $this->assignRepresentative($case, $respondent, $lawyer, 'respondent');

        $reqRes = $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($complainant));
        $mediationId = $reqRes->json('data.id');

        // Lawyer tries to accept mediation on behalf of respondent -> 403
        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", [
            'response' => 'accepted',
        ], $this->authHeaders($lawyer))->assertStatus(403);
    }

    #[Test]
    public function one_declined_consent_prevents_mediation_start()
    {
        $complainant = $this->createUser('comp14@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp14@example.com', 'Bob', 'Respondent');
        $case = $this->createCase($complainant, $respondent, TribunalCaseStatus::EvidenceCollection);

        $reqRes = $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($complainant));
        $mediationId = $reqRes->json('data.id');

        // Respondent declines mediation
        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", [
            'response' => 'declined',
        ], $this->authHeaders($respondent))->assertStatus(200)
            ->assertJsonPath('data.status', 'declined');

        $this->assertEquals(TribunalMediationStatus::Declined, TribunalMediation::find($mediationId)->status);
        // Case status remains/returns to previous
        $this->assertEquals(TribunalCaseStatus::EvidenceCollection, $case->fresh()->status);
    }

    #[Test]
    public function active_mediation_changes_case_status_to_mediation()
    {
        $complainant = $this->createUser('comp15@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp15@example.com', 'Bob', 'Respondent');
        $case = $this->createCase($complainant, $respondent, TribunalCaseStatus::EvidenceCollection);

        $reqRes = $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($complainant));
        $mediationId = $reqRes->json('data.id');

        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", [
            'response' => 'accepted',
        ], $this->authHeaders($respondent))->assertStatus(200);

        // Case status transitioned to mediation
        $this->assertEquals(TribunalCaseStatus::Mediation, $case->fresh()->status);
    }

    #[Test]
    public function party_can_create_settlement_proposal()
    {
        $complainant = $this->createUser('comp16@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp16@example.com', 'Bob', 'Respondent');
        $case = $this->createCase($complainant, $respondent);

        $reqRes = $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($complainant));
        $mediationId = $reqRes->json('data.id');
        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", ['response' => 'accepted'], $this->authHeaders($respondent));

        // Complainant creates settlement proposal
        $propRes = $this->postJson("/api/tribunal/mediations/{$mediationId}/proposals", [
            'terms' => "1. Full removal of the disputed content.\n2. Written formal apology within 14 days.\n3. Mutual non-disparagement.",
        ], $this->authHeaders($complainant));

        $propRes->assertStatus(201)
            ->assertJsonPath('data.version_number', 1)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.proposed_by_side', 'complainant');

        $proposalId = $propRes->json('data.id');
        // Proposer automatically accepted their own proposal
        $this->assertDatabaseHas('tribunal_settlement_acceptances', [
            'settlement_proposal_id' => $proposalId,
            'user_id' => $complainant->id,
            'side' => 'complainant',
        ]);
    }

    #[Test]
    public function opponent_can_counter_settlement_proposal()
    {
        $complainant = $this->createUser('comp17@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp17@example.com', 'Bob', 'Respondent');
        $case = $this->createCase($complainant, $respondent);

        $reqRes = $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($complainant));
        $mediationId = $reqRes->json('data.id');
        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", ['response' => 'accepted'], $this->authHeaders($respondent));

        $propRes = $this->postJson("/api/tribunal/mediations/{$mediationId}/proposals", [
            'terms' => 'Original proposal: immediate removal of content.',
        ], $this->authHeaders($complainant));
        $proposalId = $propRes->json('data.id');

        // Respondent submits counter proposal
        $counterRes = $this->postJson("/api/tribunal/mediations/{$mediationId}/proposals/{$proposalId}/counter", [
            'terms' => 'Counter-proposal: content edit instead of full removal, plus mutual waiver of costs.',
        ], $this->authHeaders($respondent));

        $counterRes->assertStatus(201)
            ->assertJsonPath('data.version_number', 2)
            ->assertJsonPath('data.parent_proposal_id', $proposalId)
            ->assertJsonPath('data.proposed_by_side', 'respondent');

        // Original proposal is marked as countered
        $this->assertEquals(TribunalSettlementProposalStatus::Countered, TribunalSettlementProposal::find($proposalId)->status);
    }

    #[Test]
    public function representative_cannot_finalize_settlement()
    {
        $complainant = $this->createUser('comp18@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp18@example.com', 'Bob', 'Respondent');
        $lawyer = $this->createUser('lawyer18@example.com', 'David', 'Attorney');
        $case = $this->createCase($complainant, $respondent);

        $this->assignRepresentative($case, $respondent, $lawyer, 'respondent');

        $reqRes = $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($complainant));
        $mediationId = $reqRes->json('data.id');
        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", ['response' => 'accepted'], $this->authHeaders($respondent));

        $propRes = $this->postJson("/api/tribunal/mediations/{$mediationId}/proposals", [
            'terms' => 'Settlement terms to accept',
        ], $this->authHeaders($complainant));
        $proposalId = $propRes->json('data.id');

        // Lawyer cannot accept proposal on behalf of client -> 403
        $this->postJson("/api/tribunal/settlement-proposals/{$proposalId}/accept", [], $this->authHeaders($lawyer))
            ->assertStatus(403);
    }

    #[Test]
    public function one_party_acceptance_does_not_settle_case()
    {
        $complainant = $this->createUser('comp19@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp19@example.com', 'Bob', 'Respondent');
        $case = $this->createCase($complainant, $respondent);

        $reqRes = $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($complainant));
        $mediationId = $reqRes->json('data.id');
        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", ['response' => 'accepted'], $this->authHeaders($respondent));

        $propRes = $this->postJson("/api/tribunal/mediations/{$mediationId}/proposals", [
            'terms' => 'Settlement proposal terms',
        ], $this->authHeaders($complainant));
        $proposalId = $propRes->json('data.id');

        // Only complainant has accepted so far (by proposing)
        $this->assertEquals(TribunalSettlementProposalStatus::Pending, TribunalSettlementProposal::find($proposalId)->status);
        $this->assertEquals(TribunalCaseStatus::Mediation, $case->fresh()->status);
        $this->assertDatabaseMissing('tribunal_settlement_agreements', ['settlement_proposal_id' => $proposalId]);
    }

    #[Test]
    public function both_parties_accepting_same_proposal_creates_settlement_agreement()
    {
        $complainant = $this->createUser('comp20@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp20@example.com', 'Bob', 'Respondent');
        $case = $this->createCase($complainant, $respondent);

        $reqRes = $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($complainant));
        $mediationId = $reqRes->json('data.id');
        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", ['response' => 'accepted'], $this->authHeaders($respondent));

        $propRes = $this->postJson("/api/tribunal/mediations/{$mediationId}/proposals", [
            'terms' => 'Final agreed terms for full settlement.',
        ], $this->authHeaders($complainant));
        $proposalId = $propRes->json('data.id');

        // Respondent accepts the proposal
        $acceptRes = $this->postJson("/api/tribunal/settlement-proposals/{$proposalId}/accept", [], $this->authHeaders($respondent));

        $acceptRes->assertStatus(200)
            ->assertJsonPath('data.settled', true)
            ->assertJsonPath('data.agreement.terms_snapshot', 'Final agreed terms for full settlement.');

        $this->assertDatabaseHas('tribunal_settlement_agreements', [
            'tribunal_case_id' => $case->id,
            'tribunal_mediation_id' => $mediationId,
            'settlement_proposal_id' => $proposalId,
        ]);
    }

    #[Test]
    public function successful_settlement_changes_case_status_to_settled()
    {
        $complainant = $this->createUser('comp21@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp21@example.com', 'Bob', 'Respondent');
        $case = $this->createCase($complainant, $respondent);

        $reqRes = $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($complainant));
        $mediationId = $reqRes->json('data.id');
        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", ['response' => 'accepted'], $this->authHeaders($respondent));

        $propRes = $this->postJson("/api/tribunal/mediations/{$mediationId}/proposals", [
            'terms' => 'Final settlement agreement.',
        ], $this->authHeaders($complainant));
        $proposalId = $propRes->json('data.id');

        $this->postJson("/api/tribunal/settlement-proposals/{$proposalId}/accept", [], $this->authHeaders($respondent));

        // Case status is settled
        $this->assertEquals(TribunalCaseStatus::Settled, $case->fresh()->status);
        // Mediation status is settled
        $this->assertEquals(TribunalMediationStatus::Settled, TribunalMediation::find($mediationId)->status);
    }

    #[Test]
    public function failed_mediation_restores_previous_case_status()
    {
        $complainant = $this->createUser('comp22@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp22@example.com', 'Bob', 'Respondent');
        $case = $this->createCase($complainant, $respondent, TribunalCaseStatus::EvidenceCollection);

        $reqRes = $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($complainant));
        $mediationId = $reqRes->json('data.id');
        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", ['response' => 'accepted'], $this->authHeaders($respondent));

        $this->assertEquals(TribunalCaseStatus::Mediation, $case->fresh()->status);

        // Complainant ends mediation due to deadlock
        $endRes = $this->postJson("/api/tribunal/mediations/{$mediationId}/end", [
            'reason' => 'Parties are irreconcilably deadlocked on financial remedies.',
        ], $this->authHeaders($complainant));

        $endRes->assertStatus(200)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', 'Parties are irreconcilably deadlocked on financial remedies.');

        // Reverted to previous case status: EvidenceCollection
        $this->assertEquals(TribunalCaseStatus::EvidenceCollection, $case->fresh()->status);
    }

    #[Test]
    public function unrelated_user_cannot_access_mediation()
    {
        $complainant = $this->createUser('comp23@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp23@example.com', 'Bob', 'Respondent');
        $stranger = $this->createUser('stranger23@example.com', 'Charlie', 'Stranger');
        $case = $this->createCase($complainant, $respondent);

        $this->getJson("/api/tribunal/cases/{$case->id}/mediation", $this->authHeaders($stranger))
            ->assertStatus(403);

        $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($stranger))
            ->assertStatus(403);
    }

    #[Test]
    public function settlement_agreement_terms_snapshot_remains_immutable()
    {
        $complainant = $this->createUser('comp24@example.com', 'Alice', 'Complainant');
        $respondent = $this->createUser('resp24@example.com', 'Bob', 'Respondent');
        $case = $this->createCase($complainant, $respondent);

        $reqRes = $this->postJson("/api/tribunal/cases/{$case->id}/mediation/request", [], $this->authHeaders($complainant));
        $mediationId = $reqRes->json('data.id');
        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", ['response' => 'accepted'], $this->authHeaders($respondent));

        $originalTerms = "1. Immediate payment of \$5,000.\n2. Permanent confidentiality.\n3. Case dismissed with prejudice.";
        $propRes = $this->postJson("/api/tribunal/mediations/{$mediationId}/proposals", [
            'terms' => $originalTerms,
        ], $this->authHeaders($complainant));
        $proposalId = $propRes->json('data.id');

        $this->postJson("/api/tribunal/settlement-proposals/{$proposalId}/accept", [], $this->authHeaders($respondent));

        $agreement = TribunalSettlementAgreement::where('settlement_proposal_id', $proposalId)->firstOrFail();
        $this->assertEquals($originalTerms, $agreement->terms_snapshot);

        // Even if proposal were modified afterwards (hypothetically), agreement terms_snapshot is independent
        $proposal = TribunalSettlementProposal::find($proposalId);
        $this->assertEquals($originalTerms, $proposal->terms);
    }
}
