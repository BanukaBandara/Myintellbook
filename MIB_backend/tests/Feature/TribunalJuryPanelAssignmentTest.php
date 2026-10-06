<?php

namespace Tests\Feature;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalEvidenceType;
use App\Enums\TribunalJuryPanelAssignmentMethod;
use App\Enums\TribunalJuryPanelAssignmentStatus;
use App\Enums\TribunalJuryPanelStatus;
use App\Enums\TribunalPartyRole;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\TribunalCase;
use App\Models\TribunalEvidence;
use App\Models\TribunalJuryAssignment;
use App\Models\TribunalJuryPanel;
use App\Models\TribunalJuryPanelAssignment;
use App\Models\User;
use App\Models\profession;
use App\Services\Tribunal\TribunalEvidenceService;
use App\Services\Tribunal\TribunalJuryPanelAssignmentService;
use App\Services\Tribunal\TribunalJuryPanelService;
use App\Services\Tribunal\TribunalRespondentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TribunalJuryPanelAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Storage::fake('private');

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

    protected function createTribunalCase(User $complainant, User $respondent, string $status = 'awaiting_respondent'): TribunalCase
    {
        $case = TribunalCase::create([
            'created_by' => $complainant->id,
            'title' => 'Test Dispute ' . Str::random(5),
            'category' => 'Commercial',
            'description' => 'Test case description.',
            'requested_resolution' => 'Damages and rectification.',
            'status' => $status,
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

    #[Test]
    public function test_01_case_reaching_jury_stage_automatically_gets_active_jury_panel(): void
    {
        [$panel, $panelUser] = $this->createJuryPanel('Panel Alpha', 'panel_alpha@test.com', 'active');

        $complainant = $this->createUser('comp1@test.com');
        $respondent = $this->createUser('resp1@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        $respondentService = app(TribunalRespondentService::class);
        $respondentService->acknowledge($case, $respondent->id);

        $updatedCase = $respondentService->submitResponse($case, [
            'position' => 'deny',
            'response_text' => 'We dispute all allegations completely.',
        ], $respondent->id);

        $this->assertDatabaseHas('tribunal_jury_panel_assignments', [
            'tribunal_case_id' => $case->id,
            'tribunal_jury_panel_id' => $panel->id,
            'status' => 'active',
            'assignment_method' => 'automatic',
        ]);

        $this->assertEquals(TribunalCaseStatus::EvidenceCollection, $updatedCase->fresh()->status);
    }

    #[Test]
    public function test_02_old_individual_adjudicator_assignment_is_not_created_for_new_case(): void
    {
        [$panel, $panelUser] = $this->createJuryPanel('Panel Alpha', 'panel_alpha2@test.com', 'active');

        $complainant = $this->createUser('comp2@test.com');
        $respondent = $this->createUser('resp2@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        $respondentService = app(TribunalRespondentService::class);
        $respondentService->acknowledge($case, $respondent->id);
        $respondentService->submitResponse($case, [
            'position' => 'deny',
            'response_text' => 'Disputed in full.',
        ], $respondent->id);

        // Old TribunalJuryAssignment must NOT be created
        $this->assertDatabaseMissing('tribunal_jury_assignments', [
            'tribunal_case_id' => $case->id,
        ]);
        $this->assertCount(0, TribunalJuryAssignment::where('tribunal_case_id', $case->id)->get());
    }

    #[Test]
    public function test_03_only_active_panel_is_eligible(): void
    {
        [$activePanel] = $this->createJuryPanel('Active Panel', 'active@test.com', 'active');

        $complainant = $this->createUser('comp3@test.com');
        $respondent = $this->createUser('resp3@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        $service = app(TribunalJuryPanelAssignmentService::class);
        $assignment = $service->assignCase($case);

        $this->assertNotNull($assignment);
        $this->assertEquals($activePanel->id, $assignment->tribunal_jury_panel_id);
    }

    #[Test]
    public function test_04_inactive_panel_is_ignored(): void
    {
        [$inactivePanel] = $this->createJuryPanel('Inactive Panel', 'inactive@test.com', 'inactive');
        [$activePanel] = $this->createJuryPanel('Active Panel', 'active2@test.com', 'active');

        $complainant = $this->createUser('comp4@test.com');
        $respondent = $this->createUser('resp4@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        $service = app(TribunalJuryPanelAssignmentService::class);
        $assignment = $service->assignCase($case);

        $this->assertNotNull($assignment);
        $this->assertEquals($activePanel->id, $assignment->tribunal_jury_panel_id);
        $this->assertNotEquals($inactivePanel->id, $assignment->tribunal_jury_panel_id);
    }

    #[Test]
    public function test_05_suspended_panel_is_ignored(): void
    {
        [$suspendedPanel] = $this->createJuryPanel('Suspended Panel', 'suspended@test.com', 'suspended');
        [$activePanel] = $this->createJuryPanel('Active Panel', 'active3@test.com', 'active');

        $complainant = $this->createUser('comp5@test.com');
        $respondent = $this->createUser('resp5@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        $service = app(TribunalJuryPanelAssignmentService::class);
        $assignment = $service->assignCase($case);

        $this->assertNotNull($assignment);
        $this->assertEquals($activePanel->id, $assignment->tribunal_jury_panel_id);
        $this->assertNotEquals($suspendedPanel->id, $assignment->tribunal_jury_panel_id);
    }

    #[Test]
    public function test_06_if_one_active_panel_exists_it_receives_case(): void
    {
        [$soloPanel] = $this->createJuryPanel('Solo Active Panel', 'solo@test.com', 'active');

        $complainant = $this->createUser('comp6@test.com');
        $respondent = $this->createUser('resp6@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        $service = app(TribunalJuryPanelAssignmentService::class);
        $assignment = $service->assignCase($case);

        $this->assertNotNull($assignment);
        $this->assertEquals($soloPanel->id, $assignment->tribunal_jury_panel_id);
    }

    #[Test]
    public function test_07_multiple_panels_use_fair_lowest_workload_assignment(): void
    {
        [$panel1] = $this->createJuryPanel('Panel 1', 'p1@test.com', 'active');
        [$panel2] = $this->createJuryPanel('Panel 2', 'p2@test.com', 'active');

        $service = app(TribunalJuryPanelAssignmentService::class);

        // Pre-assign 2 cases to panel 1, and 0 cases to panel 2
        $comp = $this->createUser('c@test.com');
        $resp = $this->createUser('r@test.com');

        $caseA = $this->createTribunalCase($comp, $resp);
        $caseB = $this->createTribunalCase($comp, $resp);

        TribunalJuryPanelAssignment::create([
            'tribunal_case_id' => $caseA->id,
            'tribunal_jury_panel_id' => $panel1->id,
            'status' => TribunalJuryPanelAssignmentStatus::Active,
            'assignment_method' => TribunalJuryPanelAssignmentMethod::Automatic,
            'assigned_at' => now()->subHours(2),
        ]);

        TribunalJuryPanelAssignment::create([
            'tribunal_case_id' => $caseB->id,
            'tribunal_jury_panel_id' => $panel1->id,
            'status' => TribunalJuryPanelAssignmentStatus::Active,
            'assignment_method' => TribunalJuryPanelAssignmentMethod::Automatic,
            'assigned_at' => now()->subHour(),
        ]);

        // When a new case is assigned, it MUST go to panel 2 because panel 2 has 0 active cases
        $newCase = $this->createTribunalCase($comp, $resp);
        $assignment = $service->assignCase($newCase);

        $this->assertNotNull($assignment);
        $this->assertEquals($panel2->id, $assignment->tribunal_jury_panel_id);
    }

    #[Test]
    public function test_08_no_active_panels_leaves_case_unassigned_safely(): void
    {
        // No active panels exist
        $complainant = $this->createUser('comp8@test.com');
        $respondent = $this->createUser('resp8@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        $service = app(TribunalJuryPanelAssignmentService::class);
        $assignment = $service->assignCase($case);

        $this->assertNull($assignment);
        $this->assertEquals(TribunalCaseStatus::JurySelection, $case->fresh()->status);
        $this->assertDatabaseHas('tribunal_case_events', [
            'tribunal_case_id' => $case->id,
            'event_type' => 'jury_panel_assignment_unavailable',
        ]);
    }

    #[Test]
    public function test_09_duplicate_assignment_service_call_does_not_create_duplicate_active_assignment(): void
    {
        [$panel] = $this->createJuryPanel('Panel Safe', 'safe@test.com', 'active');

        $complainant = $this->createUser('comp9@test.com');
        $respondent = $this->createUser('resp9@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        $service = app(TribunalJuryPanelAssignmentService::class);
        $first = $service->assignCase($case);
        $second = $service->assignCase($case);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, TribunalJuryPanelAssignment::where('tribunal_case_id', $case->id)->count());
    }

    #[Test]
    public function test_10_assigned_jury_panel_sees_case_in_get_api_jury_cases(): void
    {
        [$panel1, $panelUser1] = $this->createJuryPanel('Panel One', 'pone@test.com', 'active');
        [$panel2, $panelUser2] = $this->createJuryPanel('Panel Two', 'ptwo@test.com', 'active');

        $complainant = $this->createUser('comp10@test.com');
        $respondent = $this->createUser('resp10@test.com');

        $case1 = $this->createTribunalCase($complainant, $respondent);
        $case2 = $this->createTribunalCase($complainant, $respondent);

        $service = app(TribunalJuryPanelAssignmentService::class);
        $service->assignCase($case1); // goes to panel1
        $service->assignCase($case2); // goes to panel2

        // Panel 1 sees case 1 only
        $response1 = $this->withHeaders($this->authHeaders($panelUser1))
            ->getJson('/api/jury/cases');

        $response1->assertOk()
            ->assertJsonPath('status', true);

        $caseIds1 = collect($response1->json('data'))->pluck('id')->toArray();
        $this->assertContains($case1->id, $caseIds1);
        $this->assertNotContains($case2->id, $caseIds1);

        // Panel 2 sees case 2 only
        $response2 = $this->withHeaders($this->authHeaders($panelUser2))
            ->getJson('/api/jury/cases');

        $response2->assertOk()
            ->assertJsonPath('status', true);

        $caseIds2 = collect($response2->json('data'))->pluck('id')->toArray();
        $this->assertContains($case2->id, $caseIds2);
        $this->assertNotContains($case1->id, $caseIds2);
    }

    #[Test]
    public function test_11_assigned_jury_panel_can_access_get_api_jury_cases_id(): void
    {
        [$panel, $panelUser] = $this->createJuryPanel('Panel Detail', 'pdetail@test.com', 'active');

        $complainant = $this->createUser('comp11@test.com');
        $respondent = $this->createUser('resp11@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        $service = app(TribunalJuryPanelAssignmentService::class);
        $service->assignCase($case);

        $response = $this->withHeaders($this->authHeaders($panelUser))
            ->getJson("/api/jury/cases/{$case->id}");

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $case->id)
            ->assertJsonPath('data.case_number', $case->case_number)
            ->assertJsonPath('data.assigned_panel.panel_id', $panel->id)
            ->assertJsonPath('data.assigned_panel.panel_code', $panel->panel_code);
    }

    #[Test]
    public function test_12_different_jury_panel_cannot_access_case(): void
    {
        [$panel1, $panelUser1] = $this->createJuryPanel('Panel A', 'pa@test.com', 'active');
        [$panel2, $panelUser2] = $this->createJuryPanel('Panel B', 'pb@test.com', 'active');

        $complainant = $this->createUser('comp12@test.com');
        $respondent = $this->createUser('resp12@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        // Assign case exclusively to Panel 1
        TribunalJuryPanelAssignment::create([
            'tribunal_case_id' => $case->id,
            'tribunal_jury_panel_id' => $panel1->id,
            'status' => TribunalJuryPanelAssignmentStatus::Active,
            'assignment_method' => TribunalJuryPanelAssignmentMethod::Automatic,
            'assigned_at' => now(),
        ]);

        // Panel 2 attempts to view Panel 1's case -> 404 forbidden/not found
        $response = $this->withHeaders($this->authHeaders($panelUser2))
            ->getJson("/api/jury/cases/{$case->id}");

        $response->assertStatus(404);
    }

    #[Test]
    public function test_13_normal_user_cannot_access_jury_panel_case(): void
    {
        [$panel, $panelUser] = $this->createJuryPanel('Panel X', 'px@test.com', 'active');
        $normalUser = $this->createUser('normal@test.com');

        $complainant = $this->createUser('comp13@test.com');
        $respondent = $this->createUser('resp13@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        $service = app(TribunalJuryPanelAssignmentService::class);
        $service->assignCase($case);

        $response = $this->withHeaders($this->authHeaders($normalUser))
            ->getJson("/api/jury/cases/{$case->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function test_14_lawyer_cannot_access_jury_panel_case(): void
    {
        [$panel, $panelUser] = $this->createJuryPanel('Panel Y', 'py@test.com', 'active');
        $lawyer = $this->createVerifiedLawyer('lawyer@test.com');

        $complainant = $this->createUser('comp14@test.com');
        $respondent = $this->createUser('resp14@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        $service = app(TribunalJuryPanelAssignmentService::class);
        $service->assignCase($case);

        $response = $this->withHeaders($this->authHeaders($lawyer))
            ->getJson("/api/jury/cases/{$case->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function test_15_assigned_jury_panel_can_view_assigned_case_evidence(): void
    {
        [$panel, $panelUser] = $this->createJuryPanel('Panel Evidence', 'pevi@test.com', 'active');

        $complainant = $this->createUser('comp15@test.com');
        $respondent = $this->createUser('resp15@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        $service = app(TribunalJuryPanelAssignmentService::class);
        $service->assignCase($case);

        // Upload evidence as complainant
        $file = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');
        $evidenceService = app(TribunalEvidenceService::class);
        $evidence = $evidenceService->uploadEvidence($case, [
            'type' => TribunalEvidenceType::Document,
            'title' => 'Signed Contract',
            'category' => 'Document',
        ], $file, $complainant->id);

        // Assigned jury panel views evidence via case details endpoint
        $response = $this->withHeaders($this->authHeaders($panelUser))
            ->getJson("/api/jury/cases/{$case->id}");

        $response->assertOk();
        $evidenceList = $response->json('data.evidence');
        $this->assertCount(1, $evidenceList);
        $this->assertEquals('Signed Contract', $evidenceList[0]['title']);

        // Assigned jury panel downloads evidence via tribunal evidence download endpoint
        $downloadResponse = $this->withHeaders($this->authHeaders($panelUser))
            ->get("/api/tribunal/cases/{$case->id}/evidence/{$evidence->id}/download");

        $downloadResponse->assertOk();
    }

    #[Test]
    public function test_16_jury_panel_cannot_view_evidence_from_unassigned_case(): void
    {
        [$panel1, $panelUser1] = $this->createJuryPanel('Panel 1', 'p1_evi@test.com', 'active');
        [$panel2, $panelUser2] = $this->createJuryPanel('Panel 2', 'p2_evi@test.com', 'active');

        $complainant = $this->createUser('comp16@test.com');
        $respondent = $this->createUser('resp16@test.com');
        $case = $this->createTribunalCase($complainant, $respondent);

        // Assign case ONLY to Panel 1
        TribunalJuryPanelAssignment::create([
            'tribunal_case_id' => $case->id,
            'tribunal_jury_panel_id' => $panel1->id,
            'status' => TribunalJuryPanelAssignmentStatus::Active,
            'assignment_method' => TribunalJuryPanelAssignmentMethod::Automatic,
            'assigned_at' => now(),
        ]);

        // Upload evidence
        $file = UploadedFile::fake()->create('secret.pdf', 300, 'application/pdf');
        $evidenceService = app(TribunalEvidenceService::class);
        $evidence = $evidenceService->uploadEvidence($case, [
            'type' => TribunalEvidenceType::Document,
            'title' => 'Confidential Document',
            'category' => 'Document',
        ], $file, $complainant->id);

        // Panel 2 attempts to download Panel 1's evidence -> 403 Forbidden
        $response = $this->withHeaders($this->authHeaders($panelUser2))
            ->get("/api/tribunal/cases/{$case->id}/evidence/{$evidence->id}/download");

        $response->assertStatus(403);
    }

    #[Test]
    public function test_17_jury_panel_dashboard_has_no_capacity_or_max_case_concept(): void
    {
        [$panel, $panelUser] = $this->createJuryPanel('Unlimited Panel', 'unlimited@test.com', 'active');

        $response = $this->withHeaders($this->authHeaders($panelUser))
            ->getJson('/api/jury/me');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('panel.assigned_cases_count', 0)
            ->assertJsonPath('panel.active_cases_count', 0);

        // Verify no capacity or max_cases field exists
        $panelData = $response->json('panel');
        $this->assertArrayNotHasKey('capacity', $panelData);
        $this->assertArrayNotHasKey('max_cases', $panelData);
        $this->assertArrayNotHasKey('maximum_cases', $panelData);
    }
}
