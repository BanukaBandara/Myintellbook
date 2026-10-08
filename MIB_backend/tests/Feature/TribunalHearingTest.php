<?php

namespace Tests\Feature;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalHearingEntryType;
use App\Enums\TribunalHearingStatus;
use App\Enums\TribunalJuryPanelAssignmentMethod;
use App\Enums\TribunalJuryPanelAssignmentStatus;
use App\Enums\TribunalPartyRole;
use App\Enums\TribunalRepresentativeAssignmentStatus;
use App\Enums\TribunalWitnessStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\TribunalCase;
use App\Models\TribunalEvidence;
use App\Models\TribunalHearing;
use App\Models\TribunalHearingEntry;
use App\Models\TribunalJuryAssignment;
use App\Models\TribunalJuryPanel;
use App\Models\TribunalJuryPanelAssignment;
use App\Models\TribunalRepresentativeAssignment;
use App\Models\TribunalWitness;
use App\Models\User;
use App\Models\profession;
use App\Services\Tribunal\TribunalHearingService;
use App\Services\Tribunal\TribunalJuryPanelService;
use App\Services\Tribunal\TribunalWitnessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TribunalHearingTest extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);

        $cat = Category::create(['name' => 'Legal Test Category']);
        $prof = profession::create([
            'category_id' => $cat->id,
            'name' => 'Attorney',
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
            'birth_date' => '1985-05-15',
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

    protected function createJuryPanel(string $panelName, string $email, string $status = 'active'): array
    {
        $admin = $this->createUser('admin_' . Str::random(5) . '@test.com', true);
        $service = app(TribunalJuryPanelService::class);

        $panel = $service->createPanel([
            'panel_name' => $panelName,
            'email' => $email,
            'password' => 'SecurePass123!',
        ], $admin);

        if ($status !== 'active') {
            $panel->status = $status;
            $panel->save();
        }

        return [$panel, $panel->loginUser];
    }

    protected function createVerifiedLawyer(string $email): User
    {
        $lawyer = $this->createUser($email, false);

        ProfessionalVerification::create([
            'user_id' => $lawyer->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Verified,
            'registration_number' => 'BAR-' . Str::random(6),
            'enrollment_number' => 'ENR-' . Str::random(6),
            'issuing_authority' => 'Bar Association',
            'verified_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        return $lawyer;
    }

    protected function createCase(User $complainant, User $respondent, string $status = 'evidence_collection'): TribunalCase
    {
        $case = TribunalCase::create([
            'created_by' => $complainant->id,
            'title' => 'Hearing Test Case ' . Str::random(5),
            'category' => 'Commercial',
            'description' => 'Dispute requiring formal hearing.',
            'requested_resolution' => 'Damages.',
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

    protected function assignPanelToCase(TribunalJuryPanel $panel, TribunalCase $case): TribunalJuryPanelAssignment
    {
        return TribunalJuryPanelAssignment::create([
            'tribunal_case_id' => $case->id,
            'tribunal_jury_panel_id' => $panel->id,
            'status' => TribunalJuryPanelAssignmentStatus::Active,
            'assignment_method' => TribunalJuryPanelAssignmentMethod::Automatic,
            'assigned_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 1: Assigned Jury Panel can schedule hearing
    // -------------------------------------------------------------------------
    #[Test]
    public function test_01_assigned_jury_panel_can_schedule_hearing(): void
    {
        $comp = $this->createUser('comp1@test.com');
        $resp = $this->createUser('resp1@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel One', 'panel1@jury.test');
        $this->assignPanelToCase($panel, $case);

        $response = $this->postJson("/api/jury/cases/{$case->id}/hearings", [
            'hearing_type' => 'formal',
            'scheduled_at' => '2026-10-10T10:00:00',
            'location_type' => 'online',
            'notes' => 'Formal hearing for contract dispute.',
        ], $this->authHeaders($juryUser));

        $response->assertStatus(201);
        $response->assertJsonPath('hearing.hearing_type', 'formal');
        $response->assertJsonPath('hearing.status', 'scheduled');

        $this->assertDatabaseHas('tribunal_hearings', [
            'tribunal_case_id' => $case->id,
            'tribunal_jury_panel_id' => $panel->id,
            'status' => 'scheduled',
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 2: Unassigned Jury Panel cannot schedule hearing
    // -------------------------------------------------------------------------
    #[Test]
    public function test_02_unassigned_jury_panel_cannot_schedule_hearing(): void
    {
        $comp = $this->createUser('comp2@test.com');
        $resp = $this->createUser('resp2@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel1, $juryUser1] = $this->createJuryPanel('Panel Assigned', 'panel_ass@jury.test');
        $this->assignPanelToCase($panel1, $case);

        [$panel2, $unassignedJuryUser] = $this->createJuryPanel('Panel Other', 'panel_other@jury.test');

        $response = $this->postJson("/api/jury/cases/{$case->id}/hearings", [
            'hearing_type' => 'formal',
            'scheduled_at' => '2026-10-10T10:00:00',
        ], $this->authHeaders($unassignedJuryUser));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 3: Normal party cannot schedule hearing
    // -------------------------------------------------------------------------
    #[Test]
    public function test_03_normal_party_cannot_schedule_hearing(): void
    {
        $comp = $this->createUser('comp3@test.com');
        $resp = $this->createUser('resp3@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Three', 'panel3@jury.test');
        $this->assignPanelToCase($panel, $case);

        // Party attempting to hit jury endpoint
        $response = $this->postJson("/api/jury/cases/{$case->id}/hearings", [
            'hearing_type' => 'formal',
        ], $this->authHeaders($comp));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 4: Active lawyer cannot schedule hearing
    // -------------------------------------------------------------------------
    #[Test]
    public function test_04_active_lawyer_cannot_schedule_hearing(): void
    {
        $comp = $this->createUser('comp4@test.com');
        $resp = $this->createUser('resp4@test.com');
        $case = $this->createCase($comp, $resp);

        $lawyer = $this->createVerifiedLawyer('lawyer4@test.com');
        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $comp->id,
            'representative_user_id' => $lawyer->id,
            'side' => 'complainant',
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        $response = $this->postJson("/api/jury/cases/{$case->id}/hearings", [
            'hearing_type' => 'formal',
        ], $this->authHeaders($lawyer));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 5: Scheduled hearing visible to parties
    // -------------------------------------------------------------------------
    #[Test]
    public function test_05_scheduled_hearing_visible_to_parties(): void
    {
        $comp = $this->createUser('comp5@test.com');
        $resp = $this->createUser('resp5@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Five', 'panel5@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, [
            'hearing_type' => 'formal',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
        ]);

        $response = $this->getJson("/api/tribunal/cases/{$case->id}/hearings", $this->authHeaders($comp));
        $response->assertStatus(200);
        $response->assertJsonPath('hearings.0.id', $hearing->id);
        $response->assertJsonPath('hearings.0.hearing_number', $hearing->hearing_number);

        $respResponse = $this->getJson("/api/tribunal/hearings/{$hearing->id}", $this->authHeaders($resp));
        $respResponse->assertStatus(200);
        $respResponse->assertJsonPath('hearing.id', $hearing->id);
    }

    // -------------------------------------------------------------------------
    // TEST 6: Complainant cannot propose witness (Witness feature disabled)
    // -------------------------------------------------------------------------
    #[Test]
    public function test_06_complainant_can_propose_witness(): void
    {
        $comp = $this->createUser('comp6@test.com');
        $resp = $this->createUser('resp6@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Six', 'panel6@jury.test');
        $this->assignPanelToCase($panel, $case);

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/witnesses", [
            'witness_name' => 'Alice Witness',
            'witness_email' => 'alice@witness.test',
            'relationship_to_case' => 'Eyewitness to delivery',
            'statement_summary' => 'Can confirm goods were rejected at dock.',
        ], $this->authHeaders($comp));

        $response->assertStatus(404);
        $this->assertDatabaseMissing('tribunal_witnesses', [
            'tribunal_case_id' => $case->id,
            'witness_name' => 'Alice Witness',
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 7: Respondent cannot propose witness (Witness feature disabled)
    // -------------------------------------------------------------------------
    #[Test]
    public function test_07_respondent_can_propose_witness(): void
    {
        $comp = $this->createUser('comp7@test.com');
        $resp = $this->createUser('resp7@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Seven', 'panel7@jury.test');
        $this->assignPanelToCase($panel, $case);

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/witnesses", [
            'witness_name' => 'Bob Quality Inspector',
            'relationship_to_case' => 'QA Lead',
            'statement_summary' => 'Tested the delivered batch.',
        ], $this->authHeaders($resp));

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // TEST 8: Lawyer cannot propose witness on behalf of client (Witness feature disabled)
    // -------------------------------------------------------------------------
    #[Test]
    public function test_08_lawyer_can_propose_witness_on_behalf_of_client(): void
    {
        $comp = $this->createUser('comp8@test.com');
        $resp = $this->createUser('resp8@test.com');
        $case = $this->createCase($comp, $resp);

        $lawyer = $this->createVerifiedLawyer('counsel8@test.com');
        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $comp->id,
            'representative_user_id' => $lawyer->id,
            'side' => 'complainant',
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Eight', 'panel8@jury.test');
        $this->assignPanelToCase($panel, $case);

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/witnesses", [
            'witness_name' => 'Expert Auditor',
            'statement_summary' => 'Financial forensic evaluation.',
        ], $this->authHeaders($lawyer));

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // TEST 9: Jury Panel cannot approve witness (Witness feature disabled)
    // -------------------------------------------------------------------------
    #[Test]
    public function test_09_jury_panel_can_approve_witness(): void
    {
        $comp = $this->createUser('comp9@test.com');
        $resp = $this->createUser('resp9@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Nine', 'panel9@jury.test');
        $this->assignPanelToCase($panel, $case);

        $witnessService = app(TribunalWitnessService::class);
        $witness = $witnessService->proposeWitness($case, $comp->id, [
            'witness_name' => 'Charlie Key Witness',
        ]);

        $response = $this->postJson("/api/jury/cases/{$case->id}/witnesses/{$witness->id}/approve", [], $this->authHeaders($juryUser));
        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // TEST 10: Jury Panel cannot reject witness (Witness feature disabled)
    // -------------------------------------------------------------------------
    #[Test]
    public function test_10_jury_panel_can_reject_witness(): void
    {
        $comp = $this->createUser('comp10@test.com');
        $resp = $this->createUser('resp10@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Ten', 'panel10@jury.test');
        $this->assignPanelToCase($panel, $case);

        $witnessService = app(TribunalWitnessService::class);
        $witness = $witnessService->proposeWitness($case, $resp->id, [
            'witness_name' => 'Irrelevant Witness',
        ]);

        $response = $this->postJson("/api/jury/cases/{$case->id}/witnesses/{$witness->id}/reject", [
            'reason' => 'Testimony does not have material bearing on contested terms.',
        ], $this->authHeaders($juryUser));

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // TEST 11: Party cannot approve witness (Witness feature disabled)
    // -------------------------------------------------------------------------
    #[Test]
    public function test_11_party_cannot_approve_witness(): void
    {
        $comp = $this->createUser('comp11@test.com');
        $resp = $this->createUser('resp11@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Eleven', 'panel11@jury.test');
        $this->assignPanelToCase($panel, $case);

        $witnessService = app(TribunalWitnessService::class);
        $witness = $witnessService->proposeWitness($case, $comp->id, [
            'witness_name' => 'Dan Witness',
        ]);

        $response = $this->postJson("/api/jury/cases/{$case->id}/witnesses/{$witness->id}/approve", [], $this->authHeaders($comp));
        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // TEST 12: Jury Panel can start hearing
    // -------------------------------------------------------------------------
    #[Test]
    public function test_12_jury_panel_can_start_hearing(): void
    {
        $comp = $this->createUser('comp12@test.com');
        $resp = $this->createUser('resp12@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Twelve', 'panel12@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);

        $response = $this->postJson("/api/jury/hearings/{$hearing->id}/start", [], $this->authHeaders($juryUser));
        $response->assertStatus(200);
        $response->assertJsonPath('hearing.status', 'active');
    }

    // -------------------------------------------------------------------------
    // TEST 13: Case status becomes hearing
    // -------------------------------------------------------------------------
    #[Test]
    public function test_13_case_status_becomes_hearing(): void
    {
        $comp = $this->createUser('comp13@test.com');
        $resp = $this->createUser('resp13@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Thirteen', 'panel13@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $this->postJson("/api/jury/hearings/{$hearing->id}/start", [], $this->authHeaders($juryUser));

        $case->refresh();
        $this->assertEquals(TribunalCaseStatus::Hearing, $case->status);
    }

    // -------------------------------------------------------------------------
    // TEST 14: Complainant can submit opening statement
    // -------------------------------------------------------------------------
    #[Test]
    public function test_14_complainant_can_submit_opening_statement(): void
    {
        $comp = $this->createUser('comp14@test.com');
        $resp = $this->createUser('resp14@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Fourteen', 'panel14@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);

        $response = $this->postJson("/api/tribunal/hearings/{$hearing->id}/entries", [
            'entry_type' => 'opening_statement',
            'body' => 'Members of the Tribunal, our evidence proves clear breach of contract.',
        ], $this->authHeaders($comp));

        $response->assertStatus(201);
        $response->assertJsonPath('entry.entry_type', 'opening_statement');
        $response->assertJsonPath('entry.participant_type', 'complainant');
    }

    // -------------------------------------------------------------------------
    // TEST 15: Respondent can submit opening statement
    // -------------------------------------------------------------------------
    #[Test]
    public function test_15_respondent_can_submit_opening_statement(): void
    {
        $comp = $this->createUser('comp15@test.com');
        $resp = $this->createUser('resp15@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Fifteen', 'panel15@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);

        $response = $this->postJson("/api/tribunal/hearings/{$hearing->id}/entries", [
            'entry_type' => 'opening_statement',
            'body' => 'The respondent fulfilled all specifications in accordance with force majeure notices.',
        ], $this->authHeaders($resp));

        $response->assertStatus(201);
        $response->assertJsonPath('entry.entry_type', 'opening_statement');
        $response->assertJsonPath('entry.participant_type', 'respondent');
    }

    // -------------------------------------------------------------------------
    // TEST 16: Lawyer statement preserves counsel identity
    // -------------------------------------------------------------------------
    #[Test]
    public function test_16_lawyer_statement_preserves_counsel_identity(): void
    {
        $comp = $this->createUser('comp16@test.com');
        $resp = $this->createUser('resp16@test.com');
        $case = $this->createCase($comp, $resp);

        $lawyer = $this->createVerifiedLawyer('counsel16@test.com');
        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $comp->id,
            'representative_user_id' => $lawyer->id,
            'side' => 'complainant',
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Sixteen', 'panel16@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);

        $response = $this->postJson("/api/tribunal/hearings/{$hearing->id}/entries", [
            'entry_type' => 'opening_statement',
            'body' => 'Appearing as counsel for the complainant, we respectfully submit...',
        ], $this->authHeaders($lawyer));

        $response->assertStatus(201);
        $response->assertJsonPath('entry.sender_id', $lawyer->id);
        $response->assertJsonPath('entry.participant_type', 'complainant_representative');
        $response->assertJsonPath('entry.side', 'complainant');
    }

    // -------------------------------------------------------------------------
    // TEST 17: Jury Panel can post hearing question
    // -------------------------------------------------------------------------
    #[Test]
    public function test_17_jury_panel_can_post_hearing_question(): void
    {
        $comp = $this->createUser('comp17@test.com');
        $resp = $this->createUser('resp17@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Seventeen', 'panel17@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);

        $response = $this->postJson("/api/jury/hearings/{$hearing->id}/questions", [
            'body' => 'Can the respondent provide invoice records from May 2026?',
            'target_side' => 'respondent',
        ], $this->authHeaders($juryUser));

        $response->assertStatus(201);
        $response->assertJsonPath('entry.entry_type', 'jury_question');
        $response->assertJsonPath('entry.target_side', 'respondent');
    }

    // -------------------------------------------------------------------------
    // TEST 18: Correct participant can respond
    // -------------------------------------------------------------------------
    #[Test]
    public function test_18_correct_participant_can_respond(): void
    {
        $comp = $this->createUser('comp18@test.com');
        $resp = $this->createUser('resp18@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Eighteen', 'panel18@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);

        $question = $hearingService->addEntry($hearing, $juryUser->id, [
            'entry_type' => TribunalHearingEntryType::JuryQuestion,
            'body' => 'Question for Respondent.',
            'target_side' => 'respondent',
        ]);

        $response = $this->postJson("/api/tribunal/hearings/{$hearing->id}/questions/{$question->id}/responses", [
            'body' => 'We confirm invoices were tendered on May 18th.',
        ], $this->authHeaders($resp));

        $response->assertStatus(201);
        $response->assertJsonPath('entry.entry_type', 'party_answer');
        $response->assertJsonPath('entry.parent_entry_id', $question->id);
    }

    // -------------------------------------------------------------------------
    // TEST 19: Witness testimony endpoint is disabled (returns 404)
    // -------------------------------------------------------------------------
    #[Test]
    public function test_19_approved_witness_testimony_can_be_recorded(): void
    {
        $comp = $this->createUser('comp19@test.com');
        $resp = $this->createUser('resp19@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Nineteen', 'panel19@jury.test');
        $this->assignPanelToCase($panel, $case);

        $witnessService = app(TribunalWitnessService::class);
        $witness = $witnessService->proposeWitness($case, $comp->id, ['witness_name' => 'Site Manager']);
        $witnessService->approveWitness($witness, $juryUser->id);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);

        $response = $this->postJson("/api/tribunal/hearings/{$hearing->id}/witnesses/{$witness->id}/testimony", [
            'testimony' => 'I supervised the unloading and confirmed physical cracks in the machinery.',
        ], $this->authHeaders($comp));

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // TEST 20: Unapproved witness cannot testify (Witness endpoint disabled - returns 404)
    // -------------------------------------------------------------------------
    #[Test]
    public function test_20_unapproved_witness_cannot_testify(): void
    {
        $comp = $this->createUser('comp20@test.com');
        $resp = $this->createUser('resp20@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel Twenty', 'panel20@jury.test');
        $this->assignPanelToCase($panel, $case);

        $witnessService = app(TribunalWitnessService::class);
        $witness = $witnessService->proposeWitness($case, $comp->id, ['witness_name' => 'Pending Witness']);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);

        $response = $this->postJson("/api/tribunal/hearings/{$hearing->id}/witnesses/{$witness->id}/testimony", [
            'testimony' => 'Should fail because not approved.',
        ], $this->authHeaders($comp));

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // TEST 21: Evidence reference must belong to same case
    // -------------------------------------------------------------------------
    #[Test]
    public function test_21_evidence_reference_must_belong_to_same_case(): void
    {
        $comp = $this->createUser('comp21@test.com');
        $resp = $this->createUser('resp21@test.com');
        $case1 = $this->createCase($comp, $resp);
        $case2 = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel TwentyOne', 'panel21@jury.test');
        $this->assignPanelToCase($panel, $case1);

        $foreignEvidence = TribunalEvidence::create([
            'tribunal_case_id' => $case2->id,
            'uploaded_by' => $comp->id,
            'evidence_number' => 'EV-9999',
            'type' => 'document',
            'title' => 'Evidence of Case 2',
            'status' => 'accepted',
            'submitted_at' => now(),
        ]);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case1, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);

        $response = $this->postJson("/api/tribunal/hearings/{$hearing->id}/entries", [
            'entry_type' => 'evidence_reference',
            'body' => 'Referencing evidence from another case.',
            'related_evidence_id' => $foreignEvidence->id,
        ], $this->authHeaders($comp));

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // TEST 22: Jury Panel can recess hearing
    // -------------------------------------------------------------------------
    #[Test]
    public function test_22_jury_panel_can_recess_hearing(): void
    {
        $comp = $this->createUser('comp22@test.com');
        $resp = $this->createUser('resp22@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel TwentyTwo', 'panel22@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);

        $response = $this->postJson("/api/jury/hearings/{$hearing->id}/recess", [], $this->authHeaders($juryUser));
        $response->assertStatus(200);
        $response->assertJsonPath('hearing.status', 'recessed');
    }

    // -------------------------------------------------------------------------
    // TEST 23: Jury Panel can resume hearing
    // -------------------------------------------------------------------------
    #[Test]
    public function test_23_jury_panel_can_resume_hearing(): void
    {
        $comp = $this->createUser('comp23@test.com');
        $resp = $this->createUser('resp23@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel TwentyThree', 'panel23@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);
        $hearingService->recessHearing($hearing, $juryUser->id);

        $response = $this->postJson("/api/jury/hearings/{$hearing->id}/resume", [], $this->authHeaders($juryUser));
        $response->assertStatus(200);
        $response->assertJsonPath('hearing.status', 'active');
    }

    // -------------------------------------------------------------------------
    // TEST 24: Normal party cannot close hearing
    // -------------------------------------------------------------------------
    #[Test]
    public function test_24_normal_party_cannot_close_hearing(): void
    {
        $comp = $this->createUser('comp24@test.com');
        $resp = $this->createUser('resp24@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel TwentyFour', 'panel24@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);

        $response = $this->postJson("/api/jury/hearings/{$hearing->id}/close", [], $this->authHeaders($comp));
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 25: Jury Panel can close hearing
    // -------------------------------------------------------------------------
    #[Test]
    public function test_25_jury_panel_can_close_hearing(): void
    {
        $comp = $this->createUser('comp25@test.com');
        $resp = $this->createUser('resp25@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel TwentyFive', 'panel25@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);

        $response = $this->postJson("/api/jury/hearings/{$hearing->id}/close", [], $this->authHeaders($juryUser));
        $response->assertStatus(200);
        $response->assertJsonPath('hearing.status', 'completed');
    }

    // -------------------------------------------------------------------------
    // TEST 26: Hearing status becomes completed
    // -------------------------------------------------------------------------
    #[Test]
    public function test_26_hearing_status_becomes_completed(): void
    {
        $comp = $this->createUser('comp26@test.com');
        $resp = $this->createUser('resp26@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel TwentySix', 'panel26@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);
        $hearingService->closeHearing($hearing, $juryUser->id);

        $hearing->refresh();
        $this->assertEquals(TribunalHearingStatus::Completed, $hearing->status);
        $this->assertNotNull($hearing->ended_at);
    }

    // -------------------------------------------------------------------------
    // TEST 27: Case status becomes deliberation
    // -------------------------------------------------------------------------
    #[Test]
    public function test_27_case_status_becomes_deliberation(): void
    {
        $comp = $this->createUser('comp27@test.com');
        $resp = $this->createUser('resp27@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel TwentySeven', 'panel27@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);
        $hearingService->closeHearing($hearing, $juryUser->id);

        $case->refresh();
        $this->assertEquals(TribunalCaseStatus::Deliberation, $case->status);
    }

    // -------------------------------------------------------------------------
    // TEST 28: No final decision is automatically created
    // -------------------------------------------------------------------------
    #[Test]
    public function test_28_no_final_decision_is_automatically_created(): void
    {
        $comp = $this->createUser('comp28@test.com');
        $resp = $this->createUser('resp28@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel TwentyEight', 'panel28@jury.test');
        $this->assignPanelToCase($panel, $case);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);
        $hearingService->startHearing($hearing, $juryUser->id);
        $hearingService->closeHearing($hearing, $juryUser->id);

        $case->refresh();
        $this->assertNotEquals(TribunalCaseStatus::Decided, $case->status);
        $this->assertNotEquals(TribunalCaseStatus::Closed, $case->status);
        $this->assertNotEquals(TribunalCaseStatus::Settled, $case->status);
    }

    // -------------------------------------------------------------------------
    // TEST 29: Unrelated user receives 403
    // -------------------------------------------------------------------------
    #[Test]
    public function test_29_unrelated_user_receives_403(): void
    {
        $comp = $this->createUser('comp29@test.com');
        $resp = $this->createUser('resp29@test.com');
        $case = $this->createCase($comp, $resp);

        $unrelated = $this->createUser('unrelated29@test.com');

        $response = $this->getJson("/api/tribunal/cases/{$case->id}/hearings", $this->authHeaders($unrelated));
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 30: Unassigned Jury Panel receives 403
    // -------------------------------------------------------------------------
    #[Test]
    public function test_30_unassigned_jury_panel_receives_403(): void
    {
        $comp = $this->createUser('comp30@test.com');
        $resp = $this->createUser('resp30@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel1, $juryUser1] = $this->createJuryPanel('Panel Assigned 30', 'panel_ass30@jury.test');
        $this->assignPanelToCase($panel1, $case);

        [$panel2, $unassignedJuryUser] = $this->createJuryPanel('Panel Unassigned 30', 'panel_unass30@jury.test');

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser1->id, ['hearing_type' => 'formal']);

        $response = $this->getJson("/api/jury/hearings/{$hearing->id}", $this->authHeaders($unassignedJuryUser));
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 31: Old individual adjudicator has no authority
    // -------------------------------------------------------------------------
    #[Test]
    public function test_31_old_individual_adjudicator_has_no_authority(): void
    {
        $comp = $this->createUser('comp31@test.com');
        $resp = $this->createUser('resp31@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel ThirtyOne', 'panel31@jury.test');
        $this->assignPanelToCase($panel, $case);

        // Create legacy adjudicator assignment
        $oldAdjudicator = $this->createUser('old_adj31@test.com');
        TribunalJuryAssignment::create([
            'tribunal_case_id' => $case->id,
            'juror_id' => $oldAdjudicator->id,
            'status' => \App\Enums\TribunalJuryAssignmentStatus::Accepted,
            'assigned_at' => now()->subDays(5),
            'accepted_at' => now()->subDays(4),
        ]);

        $hearingService = app(TribunalHearingService::class);
        $hearing = $hearingService->scheduleHearing($case, $juryUser->id, ['hearing_type' => 'formal']);

        // Old adjudicator attempting to start hearing
        $response = $this->postJson("/api/jury/hearings/{$hearing->id}/start", [], $this->authHeaders($oldAdjudicator));
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 32: No private Jury messaging endpoint exists
    // -------------------------------------------------------------------------
    #[Test]
    public function test_32_no_private_jury_messaging_endpoint_exists(): void
    {
        $comp = $this->createUser('comp32@test.com');
        $resp = $this->createUser('resp32@test.com');
        $case = $this->createCase($comp, $resp);

        [$panel, $juryUser] = $this->createJuryPanel('Panel ThirtyTwo', 'panel32@jury.test');
        $this->assignPanelToCase($panel, $case);

        // Any attempt to create private direct message channel with jury panel returns 404/405/403
        $response1 = $this->postJson("/api/jury/cases/{$case->id}/private-chat", [], $this->authHeaders($juryUser));
        $this->assertTrue(in_array($response1->status(), [404, 405]));

        $response2 = $this->postJson("/api/tribunal/cases/{$case->id}/jury-private-chat", [], $this->authHeaders($comp));
        $this->assertTrue(in_array($response2->status(), [404, 405]));
    }
}
