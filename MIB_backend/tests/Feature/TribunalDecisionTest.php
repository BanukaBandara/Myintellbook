<?php

namespace Tests\Feature;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalDecisionOrderStatus;
use App\Enums\TribunalDecisionOrderType;
use App\Enums\TribunalDecisionOutcome;
use App\Enums\TribunalDecisionStatus;
use App\Enums\TribunalDeliberationNoteType;
use App\Enums\TribunalDeliberationStatus;
use App\Enums\TribunalFindingConclusion;
use App\Enums\TribunalFindingType;
use App\Enums\TribunalHearingEntryType;
use App\Enums\TribunalHearingLocationType;
use App\Enums\TribunalHearingStatus;
use App\Enums\TribunalHearingType;
use App\Enums\TribunalJuryPanelAssignmentMethod;
use App\Enums\TribunalJuryPanelAssignmentStatus;
use App\Enums\TribunalPartyRole;
use App\Enums\TribunalRepresentativeAssignmentStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\TribunalCase;
use App\Models\TribunalClientLawyerChatRoom;
use App\Models\TribunalDecision;
use App\Models\TribunalDecisionOrder;
use App\Models\TribunalDeliberation;
use App\Models\TribunalDeliberationNote;
use App\Models\TribunalEvidence;
use App\Models\TribunalFinding;
use App\Models\TribunalHearing;
use App\Models\TribunalHearingEntry;
use App\Models\TribunalJuryPanel;
use App\Models\TribunalJuryPanelAssignment;
use App\Models\TribunalRepresentativeAssignment;
use App\Models\TribunalWitness;
use App\Models\User;
use App\Models\profession;
use App\Services\Tribunal\TribunalDecisionService;
use App\Services\Tribunal\TribunalDeliberationService;
use App\Services\Tribunal\TribunalJuryPanelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TribunalDecisionTest extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
        config(['tribunal.appeal_window_days' => 14]);

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

    protected function createCase(User $complainant, User $respondent, string $status = 'deliberation'): TribunalCase
    {
        $case = TribunalCase::create([
            'created_by' => $complainant->id,
            'title' => 'Adjudication Case ' . Str::random(5),
            'category' => 'Commercial',
            'description' => 'Commercial dispute requiring adjudication.',
            'requested_resolution' => 'Full refund and apology.',
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

    protected function createCompletedHearing(TribunalCase $case, TribunalJuryPanel $panel, User $creator): TribunalHearing
    {
        return TribunalHearing::create([
            'tribunal_case_id' => $case->id,
            'tribunal_jury_panel_id' => $panel->id,
            'hearing_number' => 'HRG-' . Str::random(6),
            'hearing_type' => TribunalHearingType::Formal,
            'status' => TribunalHearingStatus::Completed,
            'scheduled_at' => now()->subDays(2),
            'started_at' => now()->subDays(2)->addHour(),
            'ended_at' => now()->subDays(2)->addHours(3),
            'location_type' => TribunalHearingLocationType::Online,
            'meeting_link' => 'https://tribunal.myintellbook.com/hearing/room-test',
            'created_by' => $creator->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 1: Assigned Jury Panel can open deliberation
    // -------------------------------------------------------------------------
    #[Test]
    public function test_01_assigned_jury_panel_can_open_deliberation(): void
    {
        $complainant = $this->createUser('c1@test.com');
        $respondent = $this->createUser('r1@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel One', 'panel1@test.com');
        $this->assignPanelToCase($panel, $case);

        $response = $this->getJson("/api/jury/cases/{$case->id}/deliberation", $this->authHeaders($panelUser));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'deliberation' => ['id', 'status', 'opened_at'],
                'findings',
                'decision',
                'dossier' => ['case', 'evidence', 'witnesses', 'hearing'],
            ],
        ]);
        $this->assertEquals('open', $response->json('data.deliberation.status'));
    }

    // -------------------------------------------------------------------------
    // TEST 2: Unassigned Jury Panel gets 403
    // -------------------------------------------------------------------------
    #[Test]
    public function test_02_unassigned_jury_panel_gets_403(): void
    {
        $complainant = $this->createUser('c2@test.com');
        $respondent = $this->createUser('r2@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel1, $panelUser1] = $this->createJuryPanel('Panel 1', 'p1_2@test.com');
        [$panel2, $panelUser2] = $this->createJuryPanel('Panel 2', 'p2_2@test.com');
        $this->assignPanelToCase($panel1, $case);

        // Panel 2 attempts to access Panel 1's case deliberation
        $response = $this->getJson("/api/jury/cases/{$case->id}/deliberation", $this->authHeaders($panelUser2));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 3: Party cannot access deliberation
    // -------------------------------------------------------------------------
    #[Test]
    public function test_03_party_cannot_access_deliberation(): void
    {
        $complainant = $this->createUser('c3@test.com');
        $respondent = $this->createUser('r3@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 3', 'p3_3@test.com');
        $this->assignPanelToCase($panel, $case);

        $responseC = $this->getJson("/api/jury/cases/{$case->id}/deliberation", $this->authHeaders($complainant));
        $responseC->assertStatus(403);

        $responseR = $this->getJson("/api/jury/cases/{$case->id}/deliberation", $this->authHeaders($respondent));
        $responseR->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 4: Lawyer cannot access deliberation
    // -------------------------------------------------------------------------
    #[Test]
    public function test_04_lawyer_cannot_access_deliberation(): void
    {
        $complainant = $this->createUser('c4@test.com');
        $respondent = $this->createUser('r4@test.com');
        $case = $this->createCase($complainant, $respondent);

        $lawyer = $this->createVerifiedLawyer('lawyer4@test.com');
        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $lawyer->id,
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'side' => 'complainant',
            'accepted_at' => now(),
        ]);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 4', 'p4_4@test.com');
        $this->assignPanelToCase($panel, $case);

        $response = $this->getJson("/api/jury/cases/{$case->id}/deliberation", $this->authHeaders($lawyer));
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 5: Unrelated user cannot access deliberation
    // -------------------------------------------------------------------------
    #[Test]
    public function test_05_unrelated_user_cannot_access_deliberation(): void
    {
        $complainant = $this->createUser('c5@test.com');
        $respondent = $this->createUser('r5@test.com');
        $case = $this->createCase($complainant, $respondent);

        $stranger = $this->createUser('stranger5@test.com');

        $response = $this->getJson("/api/jury/cases/{$case->id}/deliberation", $this->authHeaders($stranger));
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 6: Super Admin cannot deliberate
    // -------------------------------------------------------------------------
    #[Test]
    public function test_06_super_admin_cannot_deliberate(): void
    {
        $complainant = $this->createUser('c6@test.com');
        $respondent = $this->createUser('r6@test.com');
        $case = $this->createCase($complainant, $respondent);

        $admin = $this->createUser('admin6@test.com', true);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 6', 'p6_6@test.com');
        $this->assignPanelToCase($panel, $case);

        $response = $this->getJson("/api/jury/cases/{$case->id}/deliberation", $this->authHeaders($admin));
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 7: Jury Panel can add private note
    // -------------------------------------------------------------------------
    #[Test]
    public function test_07_jury_panel_can_add_private_note(): void
    {
        $complainant = $this->createUser('c7@test.com');
        $respondent = $this->createUser('r7@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 7', 'p7_7@test.com');
        $this->assignPanelToCase($panel, $case);

        $response = $this->postJson("/api/jury/cases/{$case->id}/deliberation/notes", [
            'note_type' => 'evidence_analysis',
            'body' => 'The contract exhibit EV-0001 contains unambiguous clause 4.2.',
        ], $this->authHeaders($panelUser));

        $response->assertStatus(201);
        $response->assertJsonPath('data.note_type', 'evidence_analysis');
        $this->assertDatabaseHas('tribunal_deliberation_notes', [
            'body' => 'The contract exhibit EV-0001 contains unambiguous clause 4.2.',
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 8: Private note not exposed to complainant
    // -------------------------------------------------------------------------
    #[Test]
    public function test_08_private_note_not_exposed_to_complainant(): void
    {
        $complainant = $this->createUser('c8@test.com');
        $respondent = $this->createUser('r8@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 8', 'p8_8@test.com');
        $this->assignPanelToCase($panel, $case);

        $deliberation = app(TribunalDeliberationService::class)->getOrCreateDeliberation($case, $panel);
        app(TribunalDeliberationService::class)->addNote($deliberation, $panelUser, [
            'note_type' => 'credibility',
            'body' => 'Complainant witness appeared nervous during cross-examination.',
        ]);

        $response = $this->getJson("/api/tribunal/cases/{$case->id}", $this->authHeaders($complainant));
        $response->assertStatus(200);
        $this->assertStringNotContainsString('Complainant witness appeared nervous', $response->getContent());
    }

    // -------------------------------------------------------------------------
    // TEST 9: Private note not exposed to respondent
    // -------------------------------------------------------------------------
    #[Test]
    public function test_09_private_note_not_exposed_to_respondent(): void
    {
        $complainant = $this->createUser('c9@test.com');
        $respondent = $this->createUser('r9@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 9', 'p9_9@test.com');
        $this->assignPanelToCase($panel, $case);

        $deliberation = app(TribunalDeliberationService::class)->getOrCreateDeliberation($case, $panel);
        app(TribunalDeliberationService::class)->addNote($deliberation, $panelUser, [
            'note_type' => 'issue_analysis',
            'body' => 'Respondent failed to provide delivery receipt.',
        ]);

        $response = $this->getJson("/api/tribunal/cases/{$case->id}", $this->authHeaders($respondent));
        $response->assertStatus(200);
        $this->assertStringNotContainsString('Respondent failed to provide delivery receipt', $response->getContent());
    }

    // -------------------------------------------------------------------------
    // TEST 10: Private note not exposed to lawyer
    // -------------------------------------------------------------------------
    #[Test]
    public function test_10_private_note_not_exposed_to_lawyer(): void
    {
        $complainant = $this->createUser('c10@test.com');
        $respondent = $this->createUser('r10@test.com');
        $case = $this->createCase($complainant, $respondent);

        $lawyer = $this->createVerifiedLawyer('lawyer10@test.com');
        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $lawyer->id,
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'side' => 'complainant',
            'accepted_at' => now(),
        ]);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 10', 'p10_10@test.com');
        $this->assignPanelToCase($panel, $case);

        $deliberation = app(TribunalDeliberationService::class)->getOrCreateDeliberation($case, $panel);
        app(TribunalDeliberationService::class)->addNote($deliberation, $panelUser, [
            'note_type' => 'remedy_consideration',
            'body' => 'Consider $5,000 compensation recommendation.',
        ]);

        $response = $this->getJson("/api/tribunal/cases/{$case->id}", $this->authHeaders($lawyer));
        $response->assertStatus(200);
        $this->assertStringNotContainsString('Consider $5,000 compensation', $response->getContent());
    }

    // -------------------------------------------------------------------------
    // TEST 11: Jury Panel can create finding
    // -------------------------------------------------------------------------
    #[Test]
    public function test_11_jury_panel_can_create_finding(): void
    {
        $complainant = $this->createUser('c11@test.com');
        $respondent = $this->createUser('r11@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 11', 'p11_11@test.com');
        $this->assignPanelToCase($panel, $case);

        $response = $this->postJson("/api/jury/cases/{$case->id}/findings", [
            'finding_type' => 'fact',
            'title' => 'Date of Disputed Contract',
            'finding_text' => 'The contract was executed by both parties on 12 January 2026.',
            'conclusion' => 'established',
            'display_order' => 1,
            'is_public' => true,
        ], $this->authHeaders($panelUser));

        $response->assertStatus(201);
        $response->assertJsonPath('data.conclusion', 'established');
        $this->assertDatabaseHas('tribunal_findings', [
            'tribunal_case_id' => $case->id,
            'conclusion' => 'established',
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 12: Finding must belong to assigned case
    // -------------------------------------------------------------------------
    #[Test]
    public function test_12_finding_must_belong_to_assigned_case(): void
    {
        $complainant = $this->createUser('c12@test.com');
        $respondent = $this->createUser('r12@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel1, $panelUser1] = $this->createJuryPanel('Panel 12-1', 'p12_1@test.com');
        [$panel2, $panelUser2] = $this->createJuryPanel('Panel 12-2', 'p12_2@test.com');
        $this->assignPanelToCase($panel1, $case);

        $response = $this->postJson("/api/jury/cases/{$case->id}/findings", [
            'finding_type' => 'fact',
            'finding_text' => 'Unauthorized finding attempt.',
            'conclusion' => 'established',
        ], $this->authHeaders($panelUser2));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 13: Cross-case evidence reference rejected
    // -------------------------------------------------------------------------
    #[Test]
    public function test_13_cross_case_evidence_reference_rejected(): void
    {
        $complainant = $this->createUser('c13@test.com');
        $respondent = $this->createUser('r13@test.com');
        $caseA = $this->createCase($complainant, $respondent);
        $caseB = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 13', 'p13_13@test.com');
        $this->assignPanelToCase($panel, $caseA);

        // Evidence belonging to Case B
        $foreignEvidence = TribunalEvidence::create([
            'tribunal_case_id' => $caseB->id,
            'uploaded_by' => $complainant->id,
            'evidence_number' => 'EV-0099',
            'type' => 'document',
            'title' => 'Case B Evidence',
            'status' => 'accepted',
            'submitted_at' => now(),
        ]);

        $response = $this->postJson("/api/jury/cases/{$caseA->id}/findings", [
            'finding_type' => 'fact',
            'finding_text' => 'Testing cross-case evidence reference.',
            'conclusion' => 'established',
            'evidence_ids' => [$foreignEvidence->id],
        ], $this->authHeaders($panelUser));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['evidence_ids']);
    }

    // -------------------------------------------------------------------------
    // TEST 14: Cross-case hearing entry reference rejected
    // -------------------------------------------------------------------------
    #[Test]
    public function test_14_cross_case_hearing_entry_reference_rejected(): void
    {
        $complainant = $this->createUser('c14@test.com');
        $respondent = $this->createUser('r14@test.com');
        $caseA = $this->createCase($complainant, $respondent);
        $caseB = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 14', 'p14_14@test.com');
        $this->assignPanelToCase($panel, $caseA);

        $hearingB = $this->createCompletedHearing($caseB, $panel, $panelUser);
        $foreignHearingEntry = TribunalHearingEntry::create([
            'tribunal_hearing_id' => $hearingB->id,
            'sender_id' => $panelUser->id,
            'participant_type' => 'jury_panel',
            'entry_type' => TribunalHearingEntryType::OpeningStatement,
            'body' => 'Case B Hearing entry',
            'sequence_number' => 1,
        ]);

        $response = $this->postJson("/api/jury/cases/{$caseA->id}/findings", [
            'finding_type' => 'fact',
            'finding_text' => 'Testing cross-case hearing entry reference.',
            'conclusion' => 'established',
            'hearing_entry_ids' => [$foreignHearingEntry->id],
        ], $this->authHeaders($panelUser));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['hearing_entry_ids']);
    }

    // -------------------------------------------------------------------------
    // TEST 15: Jury Panel can create decision draft
    // -------------------------------------------------------------------------
    #[Test]
    public function test_15_jury_panel_can_create_decision_draft(): void
    {
        $complainant = $this->createUser('c15@test.com');
        $respondent = $this->createUser('r15@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 15', 'p15_15@test.com');
        $this->assignPanelToCase($panel, $case);

        $response = $this->postJson("/api/jury/cases/{$case->id}/decision", [
            'outcome' => 'complaint_upheld',
            'summary' => 'The tribunal finds in favor of the complainant.',
            'reasoning' => 'The respondent breached explicit warranty conditions.',
        ], $this->authHeaders($panelUser));

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.outcome', 'complaint_upheld');
        $this->assertDatabaseHas('tribunal_decisions', [
            'tribunal_case_id' => $case->id,
            'status' => 'draft',
            'outcome' => 'complaint_upheld',
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 16: Party cannot see draft
    // -------------------------------------------------------------------------
    #[Test]
    public function test_16_party_cannot_see_draft(): void
    {
        $complainant = $this->createUser('c16@test.com');
        $respondent = $this->createUser('r16@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 16', 'p16_16@test.com');
        $this->assignPanelToCase($panel, $case);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Confidential draft summary.',
            'reasoning' => 'Confidential draft reasoning.',
        ]);

        $response = $this->getJson("/api/tribunal/cases/{$case->id}/decision", $this->authHeaders($complainant));
        $response->assertStatus(200);
        $response->assertJsonPath('is_pending', true);
        $response->assertJsonPath('data', null);
    }

    // -------------------------------------------------------------------------
    // TEST 17: Lawyer cannot see draft
    // -------------------------------------------------------------------------
    #[Test]
    public function test_17_lawyer_cannot_see_draft(): void
    {
        $complainant = $this->createUser('c17@test.com');
        $respondent = $this->createUser('r17@test.com');
        $case = $this->createCase($complainant, $respondent);

        $lawyer = $this->createVerifiedLawyer('lawyer17@test.com');
        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $lawyer->id,
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'side' => 'complainant',
            'accepted_at' => now(),
        ]);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 17', 'p17_17@test.com');
        $this->assignPanelToCase($panel, $case);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Draft summary',
            'reasoning' => 'Draft reasoning',
        ]);

        $response = $this->getJson("/api/tribunal/cases/{$case->id}/decision", $this->authHeaders($lawyer));
        $response->assertStatus(200);
        $response->assertJsonPath('is_pending', true);
        $response->assertJsonPath('data', null);
    }

    // -------------------------------------------------------------------------
    // TEST 18: Jury Panel can add order
    // -------------------------------------------------------------------------
    #[Test]
    public function test_18_jury_panel_can_add_order(): void
    {
        $complainant = $this->createUser('c18@test.com');
        $respondent = $this->createUser('r18@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 18', 'p18_18@test.com');
        $this->assignPanelToCase($panel, $case);

        $response = $this->postJson("/api/jury/cases/{$case->id}/decision/orders", [
            'order_type' => 'warning',
            'title' => 'Formal Conduct Warning',
            'description' => 'The respondent must desist from misleading representations.',
            'target_side' => 'respondent',
            'deadline_at' => now()->addDays(7)->toDateString(),
        ], $this->authHeaders($panelUser));

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Formal Conduct Warning');
        $this->assertDatabaseHas('tribunal_decision_orders', [
            'title' => 'Formal Conduct Warning',
            'target_side' => 'respondent',
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 19: Jury Panel can edit draft
    // -------------------------------------------------------------------------
    #[Test]
    public function test_19_jury_panel_can_edit_draft(): void
    {
        $complainant = $this->createUser('c19@test.com');
        $respondent = $this->createUser('r19@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 19', 'p19_19@test.com');
        $this->assignPanelToCase($panel, $case);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Initial draft summary',
            'reasoning' => 'Initial draft reasoning',
        ]);

        $response = $this->patchJson("/api/jury/cases/{$case->id}/decision", [
            'summary' => 'Revised draft summary with additional clarity.',
        ], $this->authHeaders($panelUser));

        $response->assertStatus(200);
        $response->assertJsonPath('data.summary', 'Revised draft summary with additional clarity.');
    }

    // -------------------------------------------------------------------------
    // TEST 20: Cannot publish without completed hearing
    // -------------------------------------------------------------------------
    #[Test]
    public function test_20_cannot_publish_without_completed_hearing(): void
    {
        $complainant = $this->createUser('c20@test.com');
        $respondent = $this->createUser('r20@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 20', 'p20_20@test.com');
        $this->assignPanelToCase($panel, $case);

        // Hearing is NOT completed (or doesn't exist)
        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Finding 1',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary',
            'reasoning' => 'Reasoning',
        ]);

        $response = $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));
        $response->assertStatus(422);
        $this->assertStringContainsString('hearing', strtolower($response->json('message')));
    }

    // -------------------------------------------------------------------------
    // TEST 21: Cannot publish without required finding
    // -------------------------------------------------------------------------
    #[Test]
    public function test_21_cannot_publish_without_required_finding(): void
    {
        $complainant = $this->createUser('c21@test.com');
        $respondent = $this->createUser('r21@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 21', 'p21_21@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary',
            'reasoning' => 'Reasoning',
        ]);

        // Zero public findings
        $response = $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));
        $response->assertStatus(422);
        $this->assertStringContainsString('finding', strtolower($response->json('message')));
    }

    // -------------------------------------------------------------------------
    // TEST 22: Cannot publish without outcome
    // -------------------------------------------------------------------------
    #[Test]
    public function test_22_cannot_publish_without_outcome(): void
    {
        $complainant = $this->createUser('c22@test.com');
        $respondent = $this->createUser('r22@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 22', 'p22_22@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Finding 1',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => null,
            'summary' => 'Summary',
            'reasoning' => 'Reasoning',
        ]);

        $response = $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));
        $response->assertStatus(422);
        $this->assertStringContainsString('outcome', strtolower($response->json('message')));
    }

    // -------------------------------------------------------------------------
    // TEST 23: Cannot publish without reasoning
    // -------------------------------------------------------------------------
    #[Test]
    public function test_23_cannot_publish_without_reasoning(): void
    {
        $complainant = $this->createUser('c23@test.com');
        $respondent = $this->createUser('r23@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 23', 'p23_23@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Finding 1',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary',
            'reasoning' => '',
        ]);

        $response = $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));
        $response->assertStatus(422);
        $this->assertStringContainsString('reasoning', strtolower($response->json('message')));
    }

    // -------------------------------------------------------------------------
    // TEST 24: Assigned Jury Panel can publish
    // -------------------------------------------------------------------------
    #[Test]
    public function test_24_assigned_jury_panel_can_publish(): void
    {
        $complainant = $this->createUser('c24@test.com');
        $respondent = $this->createUser('r24@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 24', 'p24_24@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Finding 1',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Complete summary of decision',
            'reasoning' => 'Complete reasoning supporting determination',
        ]);

        $response = $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'final');
    }

    // -------------------------------------------------------------------------
    // TEST 25: Decision becomes final
    // -------------------------------------------------------------------------
    #[Test]
    public function test_25_decision_becomes_final(): void
    {
        $complainant = $this->createUser('c25@test.com');
        $respondent = $this->createUser('r25@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 25', 'p25_25@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Finding 1',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary',
            'reasoning' => 'Reasoning',
        ]);

        $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));

        $decision = TribunalDecision::where('tribunal_case_id', $case->id)->first();
        $this->assertEquals(TribunalDecisionStatus::Final, $decision->status);
        $this->assertNotNull($decision->published_at);
    }

    // -------------------------------------------------------------------------
    // TEST 26: Published decision visible to complainant
    // -------------------------------------------------------------------------
    #[Test]
    public function test_26_published_decision_visible_to_complainant(): void
    {
        $complainant = $this->createUser('c26@test.com');
        $respondent = $this->createUser('r26@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 26', 'p26_26@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Public Finding 26',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary 26',
            'reasoning' => 'Reasoning 26',
        ]);

        $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));

        $response = $this->getJson("/api/tribunal/cases/{$case->id}/decision", $this->authHeaders($complainant));
        $response->assertStatus(200);
        $response->assertJsonPath('data.outcome', 'complaint_upheld');
        $response->assertJsonPath('data.summary', 'Summary 26');
        $response->assertJsonPath('is_pending', false);
    }

    // -------------------------------------------------------------------------
    // TEST 27: Published decision visible to respondent
    // -------------------------------------------------------------------------
    #[Test]
    public function test_27_published_decision_visible_to_respondent(): void
    {
        $complainant = $this->createUser('c27@test.com');
        $respondent = $this->createUser('r27@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 27', 'p27_27@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Public Finding 27',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'dismissed',
            'summary' => 'Summary 27',
            'reasoning' => 'Reasoning 27',
        ]);

        $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));

        $response = $this->getJson("/api/tribunal/cases/{$case->id}/decision", $this->authHeaders($respondent));
        $response->assertStatus(200);
        $response->assertJsonPath('data.outcome', 'dismissed');
    }

    // -------------------------------------------------------------------------
    // TEST 28: Published decision visible to active lawyer
    // -------------------------------------------------------------------------
    #[Test]
    public function test_28_published_decision_visible_to_active_lawyer(): void
    {
        $complainant = $this->createUser('c28@test.com');
        $respondent = $this->createUser('r28@test.com');
        $case = $this->createCase($complainant, $respondent);

        $lawyer = $this->createVerifiedLawyer('lawyer28@test.com');
        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $lawyer->id,
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'side' => 'complainant',
            'accepted_at' => now(),
        ]);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 28', 'p28_28@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Public Finding 28',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary 28',
            'reasoning' => 'Reasoning 28',
        ]);

        $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));

        $response = $this->getJson("/api/tribunal/cases/{$case->id}/decision", $this->authHeaders($lawyer));
        $response->assertStatus(200);
        $response->assertJsonPath('data.outcome', 'complaint_upheld');
    }

    // -------------------------------------------------------------------------
    // TEST 29: Case becomes appeal_window
    // -------------------------------------------------------------------------
    #[Test]
    public function test_29_case_becomes_appeal_window(): void
    {
        $complainant = $this->createUser('c29@test.com');
        $respondent = $this->createUser('r29@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 29', 'p29_29@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Finding 29',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary',
            'reasoning' => 'Reasoning',
        ]);

        $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));

        $case->refresh();
        $this->assertEquals(TribunalCaseStatus::AppealWindow, $case->status);
    }

    // -------------------------------------------------------------------------
    // TEST 30: Appeal deadline is stored
    // -------------------------------------------------------------------------
    #[Test]
    public function test_30_appeal_deadline_is_stored(): void
    {
        $complainant = $this->createUser('c30@test.com');
        $respondent = $this->createUser('r30@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 30', 'p30_30@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Finding 30',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary',
            'reasoning' => 'Reasoning',
        ]);

        $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));

        $decision = TribunalDecision::where('tribunal_case_id', $case->id)->first();
        $this->assertNotNull($decision->appeal_deadline);
        $this->assertTrue($decision->appeal_deadline->isFuture());
    }

    // -------------------------------------------------------------------------
    // TEST 31: Deliberation becomes completed
    // -------------------------------------------------------------------------
    #[Test]
    public function test_31_deliberation_becomes_completed(): void
    {
        $complainant = $this->createUser('c31@test.com');
        $respondent = $this->createUser('r31@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 31', 'p31_31@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Finding 31',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary',
            'reasoning' => 'Reasoning',
        ]);

        $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));

        $deliberation = TribunalDeliberation::where('tribunal_case_id', $case->id)->first();
        $this->assertEquals(TribunalDeliberationStatus::Completed, $deliberation->status);
        $this->assertNotNull($deliberation->completed_at);
    }

    // -------------------------------------------------------------------------
    // TEST 32: Final decision cannot be edited
    // -------------------------------------------------------------------------
    #[Test]
    public function test_32_final_decision_cannot_be_edited(): void
    {
        $complainant = $this->createUser('c32@test.com');
        $respondent = $this->createUser('r32@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 32', 'p32_32@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Finding 32',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary',
            'reasoning' => 'Reasoning',
        ]);

        $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));

        $response = $this->patchJson("/api/jury/cases/{$case->id}/decision", [
            'summary' => 'Attempting to mutate published decision',
        ], $this->authHeaders($panelUser));

        $response->assertStatus(409);
    }

    // -------------------------------------------------------------------------
    // TEST 33: Final finding cannot be edited
    // -------------------------------------------------------------------------
    #[Test]
    public function test_33_final_finding_cannot_be_edited(): void
    {
        $complainant = $this->createUser('c33@test.com');
        $respondent = $this->createUser('r33@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 33', 'p33_33@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        $finding = app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Finding 33',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary',
            'reasoning' => 'Reasoning',
        ]);

        $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));

        $responsePatch = $this->patchJson("/api/jury/cases/{$case->id}/findings/{$finding->id}", [
            'finding_text' => 'Attempting to mutate finding after publication',
        ], $this->authHeaders($panelUser));
        $responsePatch->assertStatus(409);

        $responseDelete = $this->deleteJson("/api/jury/cases/{$case->id}/findings/{$finding->id}", [], $this->authHeaders($panelUser));
        $responseDelete->assertStatus(409);
    }

    // -------------------------------------------------------------------------
    // TEST 34: Final order cannot be edited
    // -------------------------------------------------------------------------
    #[Test]
    public function test_34_final_order_cannot_be_edited(): void
    {
        $complainant = $this->createUser('c34@test.com');
        $respondent = $this->createUser('r34@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 34', 'p34_34@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Finding 34',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        $decision = app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary',
            'reasoning' => 'Reasoning',
        ]);

        $order = app(TribunalDecisionService::class)->addOrder($decision, [
            'order_type' => 'warning',
            'title' => 'Warning 34',
            'description' => 'Description 34',
        ]);

        $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));

        $responsePatch = $this->patchJson("/api/jury/cases/{$case->id}/decision/orders/{$order->id}", [
            'title' => 'Altered Title',
        ], $this->authHeaders($panelUser));
        $responsePatch->assertStatus(409);

        $responseDelete = $this->deleteJson("/api/jury/cases/{$case->id}/decision/orders/{$order->id}", [], $this->authHeaders($panelUser));
        $responseDelete->assertStatus(409);
    }

    // -------------------------------------------------------------------------
    // TEST 35: Second publication prevented/idempotent
    // -------------------------------------------------------------------------
    #[Test]
    public function test_35_second_publication_prevented_idempotent(): void
    {
        $complainant = $this->createUser('c35@test.com');
        $respondent = $this->createUser('r35@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 35', 'p35_35@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Finding 35',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary',
            'reasoning' => 'Reasoning',
        ]);

        $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));

        // Second publish attempt
        $secondResponse = $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));
        $secondResponse->assertStatus(409);
    }

    // -------------------------------------------------------------------------
    // TEST 36: No final decision contains private deliberation notes
    // -------------------------------------------------------------------------
    #[Test]
    public function test_36_no_final_decision_contains_private_deliberation_notes(): void
    {
        $complainant = $this->createUser('c36@test.com');
        $respondent = $this->createUser('r36@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 36', 'p36_36@test.com');
        $this->assignPanelToCase($panel, $case);
        $this->createCompletedHearing($case, $panel, $panelUser);

        $deliberation = app(TribunalDeliberationService::class)->getOrCreateDeliberation($case, $panel);
        app(TribunalDeliberationService::class)->addNote($deliberation, $panelUser, [
            'note_type' => 'evidence_analysis',
            'body' => 'TOP_SECRET_DELIBERATION_NOTE_36',
        ]);

        app(TribunalDeliberationService::class)->createFinding($case, $panel, $panelUser, [
            'finding_type' => 'fact',
            'finding_text' => 'Finding 36',
            'conclusion' => 'established',
            'is_public' => true,
        ]);

        app(TribunalDecisionService::class)->saveDraft($case, $panel, $panelUser, [
            'outcome' => 'complaint_upheld',
            'summary' => 'Summary 36',
            'reasoning' => 'Reasoning 36',
        ]);

        $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));

        $partyResponse = $this->getJson("/api/tribunal/cases/{$case->id}/decision", $this->authHeaders($complainant));
        $partyResponse->assertStatus(200);
        $this->assertStringNotContainsString('TOP_SECRET_DELIBERATION_NOTE_36', $partyResponse->getContent());
        $this->assertArrayNotHasKey('notes', $partyResponse->json('data'));
    }

    // -------------------------------------------------------------------------
    // TEST 37: Old adjudicator cannot publish
    // -------------------------------------------------------------------------
    #[Test]
    public function test_37_old_adjudicator_cannot_publish(): void
    {
        $complainant = $this->createUser('c37@test.com');
        $respondent = $this->createUser('r37@test.com');
        $case = $this->createCase($complainant, $respondent);

        // Old adjudicator user without a jury panel account
        $adjudicator = $this->createUser('adjudicator37@test.com');

        $response = $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($adjudicator));
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 38: Unassigned Jury Panel cannot publish
    // -------------------------------------------------------------------------
    #[Test]
    public function test_38_unassigned_jury_panel_cannot_publish(): void
    {
        $complainant = $this->createUser('c38@test.com');
        $respondent = $this->createUser('r38@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel1, $panelUser1] = $this->createJuryPanel('Panel 38-1', 'p38_1@test.com');
        [$panel2, $panelUser2] = $this->createJuryPanel('Panel 38-2', 'p38_2@test.com');
        $this->assignPanelToCase($panel1, $case);

        $response = $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser2));
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 39: Inactive Jury Panel cannot publish
    // -------------------------------------------------------------------------
    #[Test]
    public function test_39_inactive_jury_panel_cannot_publish(): void
    {
        $complainant = $this->createUser('c39@test.com');
        $respondent = $this->createUser('r39@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 39', 'p39_39@test.com', 'suspended');
        $this->assignPanelToCase($panel, $case);

        $response = $this->postJson("/api/jury/cases/{$case->id}/decision/publish", [], $this->authHeaders($panelUser));
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 40: Private client-lawyer messages are not included
    // -------------------------------------------------------------------------
    #[Test]
    public function test_40_private_client_lawyer_messages_are_not_included(): void
    {
        $complainant = $this->createUser('c40@test.com');
        $respondent = $this->createUser('r40@test.com');
        $case = $this->createCase($complainant, $respondent);

        $lawyer = $this->createVerifiedLawyer('lawyer40@test.com');
        $repAssignment = TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $lawyer->id,
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'side' => 'complainant',
            'accepted_at' => now(),
        ]);

        $conv = \App\Models\TribunalConversation::create([
            'tribunal_case_id' => $case->id,
            'type' => \App\Enums\TribunalConversationType::ComplainantRepresentative,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $lawyer->id,
            'active' => true,
        ]);

        $conv->messages()->create([
            'sender_id' => $complainant->id,
            'body' => 'CONFIDENTIAL_PRIVILEGED_CLIENT_ADMISSION',
        ]);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 40', 'p40_40@test.com');
        $this->assignPanelToCase($panel, $case);

        $response = $this->getJson("/api/jury/cases/{$case->id}/deliberation", $this->authHeaders($panelUser));
        $response->assertStatus(200);
        $this->assertStringNotContainsString('CONFIDENTIAL_PRIVILEGED_CLIENT_ADMISSION', $response->getContent());
    }

    // -------------------------------------------------------------------------
    // TEST 41: No AI-generated automatic outcome exists
    // -------------------------------------------------------------------------
    #[Test]
    public function test_41_no_ai_generated_automatic_outcome_exists(): void
    {
        $complainant = $this->createUser('c41@test.com');
        $respondent = $this->createUser('r41@test.com');
        $case = $this->createCase($complainant, $respondent);

        [$panel, $panelUser] = $this->createJuryPanel('Panel 41', 'p41_41@test.com');
        $this->assignPanelToCase($panel, $case);

        // When deliberation opens, no automatic verdict or AI outcome is populated
        $response = $this->getJson("/api/jury/cases/{$case->id}/deliberation", $this->authHeaders($panelUser));
        $response->assertStatus(200);

        $decision = $response->json('data.decision');
        $this->assertTrue(is_null($decision) || is_null($decision['outcome'] ?? null));
    }
}
