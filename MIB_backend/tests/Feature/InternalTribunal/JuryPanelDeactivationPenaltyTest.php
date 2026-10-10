<?php

namespace Tests\Feature\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Enums\InternalReportCategory;
use App\Enums\InternalReportStatus;
use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalJuryPanelAssignmentMethod;
use App\Enums\TribunalJuryPanelAssignmentStatus;
use App\Enums\TribunalJuryPanelStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\InternalPenalty;
use App\Models\InternalReport;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\TribunalCase;
use App\Models\TribunalCaseParty;
use App\Models\TribunalJuryAssignment;
use App\Models\TribunalJuryPanel;
use App\Models\TribunalJuryPanelAssignment;
use App\Models\User;
use App\Models\profession;
use App\Notifications\InternalTribunal\ReportedJuryPanelDeactivationNotification;
use App\Services\InternalTribunal\AccountJuryPanelDisciplineService;
use App\Services\InternalTribunal\InternalPenaltyService;
use App\Services\InternalTribunal\InternalReportService;
use App\Services\Tribunal\TribunalJuryPanelAssignmentService;
use App\Services\Tribunal\TribunalJuryPanelService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JuryPanelDeactivationPenaltyTest extends TestCase
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
            'birth_date' => '1985-05-15',
        ]);

        return $user;
    }

    protected function createJuryPanel(string $panelName, string $email, string $status = 'active'): array
    {
        $admin = $this->createUser('admin_' . Str::random(5) . '@test.com', true);
        $service = app(TribunalJuryPanelService::class);

        $panel = $service->createPanel([
            'panel_name' => $panelName,
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], $admin);

        if ($status !== 'active') {
            $panel->status = TribunalJuryPanelStatus::from($status);
            $panel->save();
        }

        return [$panel, $panel->loginUser];
    }

    protected function createVerifiedLawyer(string $email): User
    {
        $user = $this->createUser($email);

        ProfessionalVerification::create([
            'user_id' => $user->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Verified,
            'registration_number' => 'BAR-' . Str::random(6),
            'enrollment_number' => 'ENR-' . Str::random(6),
            'issuing_authority' => 'Bar Association',
            'years_of_experience' => 5,
            'submitted_at' => now()->subMonths(6),
            'verified_at' => now()->subMonths(5),
            'reviewed_at' => now()->subMonths(5),
            'expires_at' => now()->addYear(),
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
            'category' => InternalReportCategory::TribunalLegalProcessMisconduct->value,
            'subject' => 'Tribunal process irregularity alleged',
            'description' => 'Detailed misconduct report for jury panel test.',
            'severity' => 'high',
        ], [], $reporter);

        $report->update(['status' => InternalReportStatus::Valid->value]);

        return $report->fresh();
    }

    protected function createTribunalCase(User $complainant, User $respondent): TribunalCase
    {
        $case = TribunalCase::create([
            'case_number' => 'TC-' . strtoupper(Str::random(8)),
            'title' => 'Test Case ' . Str::random(4),
            'category' => 'academic',
            'description' => 'Case description',
            'severity' => 'standard',
            'status' => TribunalCaseStatus::EvidenceCollection,
            'created_by' => $complainant->id,
            'submitted_at' => now(),
        ]);

        TribunalCaseParty::create([
            'tribunal_case_id' => $case->id,
            'user_id' => $complainant->id,
            'role' => \App\Enums\TribunalPartyRole::Complainant,
        ]);

        TribunalCaseParty::create([
            'tribunal_case_id' => $case->id,
            'user_id' => $respondent->id,
            'role' => \App\Enums\TribunalPartyRole::Respondent,
        ]);

        return $case;
    }

    // -------------------------------------------------------------------------
    // 1. APPLICATION (TEMPORARY & PERMANENT)
    // -------------------------------------------------------------------------

    #[Test]
    public function test_super_admin_can_apply_jury_panel_deactivation_temporary_and_permanent(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        [$panel, $panelUser] = $this->createJuryPanel('Panel Alpha', 'panel_alpha@test.com');

        $report = $this->createValidReport($reporter, $panelUser);

        // Apply temporary deactivation (30 days)
        $response = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'temporary',
                'duration_days' => 30,
                'reason' => 'Procedural irregularity under investigation.',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.action_type', InternalPenaltyType::JuryPanelDeactivation->value);

        $this->assertDatabaseHas('internal_penalties', [
            'internal_report_id' => $report->id,
            'user_id' => $panelUser->id,
            'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
            'applied_by' => $admin->id,
        ]);

        $disciplineService = app(AccountJuryPanelDisciplineService::class);
        $this->assertTrue($disciplineService->hasActiveDeactivation($panelUser));
        $this->assertTrue($disciplineService->hasActiveDeactivation($panel));
        $this->assertContains($panel->id, $disciplineService->getDeactivatedPanelIds());

        // Notification was sent
        Notification::assertSentTo($panelUser, ReportedJuryPanelDeactivationNotification::class, function ($notif) {
            return $notif->isTemporary === true && $notif->endsAt !== null;
        });

        // Authentic underlying panel status remains 'active' (zero status mutation)
        $panel->refresh();
        $this->assertEquals(TribunalJuryPanelStatus::Active, $panel->status);
    }

    // -------------------------------------------------------------------------
    // 2. TARGET VALIDATION & IMMUNITIES
    // -------------------------------------------------------------------------

    #[Test]
    public function test_target_validation_and_immunities(): void
    {
        $admin = $this->createUser('superadmin@test.com', true);
        $reporter = $this->createUser('reporter2@test.com');
        $normalUser = $this->createUser('normal@test.com');
        $lawyer = $this->createVerifiedLawyer('lawyer@test.com');
        [$panel, $panelUser] = $this->createJuryPanel('Panel Beta', 'panel_beta@test.com');

        // Normal user rejected for Jury Panel Deactivation
        $reportNormal = $this->createValidReport($reporter, $normalUser);
        $resNormal = $this->postJson(
            "/api/admin/internal-reports/{$reportNormal->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'permanent',
                'reason' => 'Attempt on normal user.',
            ],
            $this->authHeaders($admin)
        );
        $resNormal->assertStatus(422);

        // Professional lawyer rejected for Jury Panel Deactivation
        $reportLawyer = $this->createValidReport($reporter, $lawyer);
        $resLawyer = $this->postJson(
            "/api/admin/internal-reports/{$reportLawyer->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'permanent',
                'reason' => 'Attempt on lawyer.',
            ],
            $this->authHeaders($admin)
        );
        $resLawyer->assertStatus(422);

        // Super Admin rejected
        $reportAdmin = $this->createValidReport($reporter, $admin);
        $resAdmin = $this->postJson(
            "/api/admin/internal-reports/{$reportAdmin->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'permanent',
                'reason' => 'Attempt on admin.',
            ],
            $this->authHeaders($admin)
        );
        $resAdmin->assertStatus(422);

        // Non-valid report rejected
        $reportSubmitted = $this->createValidReport($reporter, $panelUser);
        $reportSubmitted->update(['status' => InternalReportStatus::Submitted->value]);
        $resSubmitted = $this->postJson(
            "/api/admin/internal-reports/{$reportSubmitted->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'permanent',
                'reason' => 'Attempt on non-valid report.',
            ],
            $this->authHeaders($admin)
        );
        $resSubmitted->assertStatus(422);

        // Other sanctions still reject Jury Panel target
        $validPanelReport = $this->createValidReport($reporter, $panelUser);
        $resSuspension = $this->postJson(
            "/api/admin/internal-reports/{$validPanelReport->id}/penalties",
            [
                'action_type' => InternalPenaltyType::PermanentSuspension->value,
                'reason' => 'Attempt account suspension on panel.',
            ],
            $this->authHeaders($admin)
        );
        $resSuspension->assertStatus(422);

        $resFeature = $this->postJson(
            "/api/admin/internal-reports/{$validPanelReport->id}/penalties",
            [
                'action_type' => InternalPenaltyType::FeatureRestriction->value,
                'penalty_value' => 'tribunal_participation',
                'restriction_duration_type' => 'permanent',
                'reason' => 'Attempt feature restriction on panel.',
            ],
            $this->authHeaders($admin)
        );
        $resFeature->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // 3. DUPLICATE ACTIVE DEACTIVATION PROTECTION
    // -------------------------------------------------------------------------

    #[Test]
    public function test_duplicate_active_deactivation_is_prevented(): void
    {
        $admin = $this->createUser('admin_dup@test.com', true);
        $reporter = $this->createUser('reporter_dup@test.com');
        [$panel, $panelUser] = $this->createJuryPanel('Panel Dup', 'panel_dup@test.com');

        $report1 = $this->createValidReport($reporter, $panelUser);
        $report2 = $this->createValidReport($reporter, $panelUser);

        // Apply first deactivation
        $res1 = $this->postJson(
            "/api/admin/internal-reports/{$report1->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'permanent',
                'reason' => 'First deactivation.',
            ],
            $this->authHeaders($admin)
        );
        $res1->assertStatus(200);

        // Attempt second duplicate active deactivation
        $res2 = $this->postJson(
            "/api/admin/internal-reports/{$report2->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'temporary',
                'duration_days' => 10,
                'reason' => 'Duplicate deactivation.',
            ],
            $this->authHeaders($admin)
        );
        $res2->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // 4. NEW CASE ASSIGNMENTS EXCLUSION & FAIR FALLBACK
    // -------------------------------------------------------------------------

    #[Test]
    public function test_new_case_assignments_exclude_deactivated_panel_and_fallback_cleanly(): void
    {
        $admin = $this->createUser('admin_assign@test.com', true);
        $reporter = $this->createUser('reporter_assign@test.com');
        $complainant = $this->createUser('comp@test.com');
        $respondent = $this->createUser('resp@test.com');

        [$panel1, $panelUser1] = $this->createJuryPanel('Panel 1', 'panel1@test.com');
        [$panel2, $panelUser2] = $this->createJuryPanel('Panel 2', 'panel2@test.com');

        $assignmentService = app(TribunalJuryPanelAssignmentService::class);

        // Initially both panels are candidate panels
        $this->assertCount(2, $assignmentService->findActivePanels());

        // Deactivate Panel 1
        $report = $this->createValidReport($reporter, $panelUser1);
        $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'permanent',
                'reason' => 'Panel 1 deactivated.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        // findActivePanels now only returns Panel 2
        $activePanels = $assignmentService->findActivePanels();
        $this->assertCount(1, $activePanels);
        $this->assertEquals($panel2->id, $activePanels->first()->id);

        // Creating and assigning a case assigns to Panel 2 (not Panel 1)
        $case = $this->createTribunalCase($complainant, $respondent);
        $assignment = $assignmentService->assignCase($case);

        $this->assertNotNull($assignment);
        $this->assertEquals($panel2->id, $assignment->tribunal_jury_panel_id);
        $this->assertNotEquals($panel1->id, $assignment->tribunal_jury_panel_id);

        // Legacy individual adjudicator rows are NOT created
        $this->assertEquals(0, TribunalJuryAssignment::where('tribunal_case_id', $case->id)->count());

        // Now also deactivate Panel 2
        $report2 = $this->createValidReport($reporter, $panelUser2);
        $this->postJson(
            "/api/admin/internal-reports/{$report2->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'permanent',
                'reason' => 'Panel 2 deactivated.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        $this->assertCount(0, $assignmentService->findActivePanels());

        // New case when all panels deactivated: remains in jury_selection gracefully
        $case2 = $this->createTribunalCase($complainant, $respondent);
        $case2->update(['status' => TribunalCaseStatus::JurySelection]);
        $assignment2 = $assignmentService->assignCase($case2);

        $this->assertNull($assignment2);
        $case2->refresh();
        $this->assertEquals(TribunalCaseStatus::JurySelection, $case2->status);
    }

    // -------------------------------------------------------------------------
    // 5. ACTIVE CASE PORTAL OPERATIONS RESTRICTION
    // -------------------------------------------------------------------------

    #[Test]
    public function test_active_case_portal_operations_are_blocked_while_preserving_assignment_records(): void
    {
        $admin = $this->createUser('admin_active@test.com', true);
        $reporter = $this->createUser('reporter_active@test.com');
        $complainant = $this->createUser('comp_act@test.com');
        $respondent = $this->createUser('resp_act@test.com');

        [$panel, $panelUser] = $this->createJuryPanel('Panel Active Ops', 'panel_active@test.com');

        // Assign a case to this panel before deactivation
        $case = $this->createTribunalCase($complainant, $respondent);
        $assignment = TribunalJuryPanelAssignment::create([
            'tribunal_case_id' => $case->id,
            'tribunal_jury_panel_id' => $panel->id,
            'status' => TribunalJuryPanelAssignmentStatus::Active,
            'assignment_method' => TribunalJuryPanelAssignmentMethod::Automatic,
            'assigned_at' => now(),
        ]);

        // Prior to deactivation, portal operations succeed
        $headers = $this->authHeaders($panelUser);
        $resMeBefore = $this->getJson('/api/jury/me', $headers);
        $resMeBefore->assertStatus(200);

        // Deactivate panel
        $report = $this->createValidReport($reporter, $panelUser);
        $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'temporary',
                'duration_days' => 14,
                'reason' => 'Investigation into active operations.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        // Historical assignment record remains completely intact and active
        $assignment->refresh();
        $this->assertEquals(TribunalJuryPanelAssignmentStatus::Active, $assignment->status);
        $this->assertEquals($panel->id, $assignment->tribunal_jury_panel_id);

        // Portal access returns 403 with panel_status deactivated
        $resMe = $this->getJson('/api/jury/me', $headers);
        $resMe->assertStatus(403);
        $resMe->assertJsonPath('panel_status', 'deactivated');

        $resCases = $this->getJson('/api/jury/cases', $headers);
        $resCases->assertStatus(403);

        $resShow = $this->getJson("/api/jury/cases/{$case->id}", $headers);
        $resShow->assertStatus(403);

        // Operational actions (e.g. posting procedural notice, message) are blocked
        $resNotice = $this->postJson("/api/jury/cases/{$case->id}/case-room/procedural-notices", [
            'body' => 'Notice should be blocked',
        ], $headers);
        $resNotice->assertStatus(403);

        // isAssignedJuryPanelUser domain check returns false
        $this->assertFalse($case->isAssignedJuryPanelUser($panelUser->id));
    }

    // -------------------------------------------------------------------------
    // 6. LOGIN CREDENTIALS AND TOKENS REMAIN VALID
    // -------------------------------------------------------------------------

    #[Test]
    public function test_login_credentials_and_tokens_remain_valid(): void
    {
        $admin = $this->createUser('admin_login@test.com', true);
        $reporter = $this->createUser('reporter_login@test.com');
        [$panel, $panelUser] = $this->createJuryPanel('Panel Login Test', 'panel_login@test.com');

        $headers = $this->authHeaders($panelUser, $rawToken);

        // Deactivate panel
        $report = $this->createValidReport($reporter, $panelUser);
        $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'permanent',
                'reason' => 'Permanent deactivation test.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        // Token was NOT revoked from api_tokens table
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $panelUser->id,
            'token' => hash('sha256', $rawToken),
        ]);

        // General authentication passes (not 401 unauthenticated)
        // It receives 403 from the jury panel portal middleware specifically
        $response = $this->getJson('/api/jury/me', $headers);
        $response->assertStatus(403);
        $response->assertJsonPath('panel_status', 'deactivated');
    }

    // -------------------------------------------------------------------------
    // 7. TEMPORARY DEACTIVATION EXPIRES AUTOMATICALLY (CARBON TRAVEL)
    // -------------------------------------------------------------------------

    #[Test]
    public function test_temporary_deactivation_expires_automatically_with_carbon_travel(): void
    {
        $admin = $this->createUser('admin_time@test.com', true);
        $reporter = $this->createUser('reporter_time@test.com');
        [$panel, $panelUser] = $this->createJuryPanel('Panel Time Test', 'panel_time@test.com');

        $report = $this->createValidReport($reporter, $panelUser);
        $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'temporary',
                'duration_days' => 5,
                'reason' => '5 days temporary deactivation.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        $disciplineService = app(AccountJuryPanelDisciplineService::class);
        $this->assertTrue($disciplineService->hasActiveDeactivation($panelUser));

        // Fast forward 6 days
        Carbon::setTestNow(now()->addDays(6));

        // Automatically eligible again without cron or database mutation
        $this->assertFalse($disciplineService->hasActiveDeactivation($panelUser));
        $this->assertFalse($disciplineService->hasActiveDeactivation($panel));
        $this->assertNotContains($panel->id, $disciplineService->getDeactivatedPanelIds());

        // Portal access resumes
        $resMe = $this->getJson('/api/jury/me', $this->authHeaders($panelUser));
        $resMe->assertStatus(200);

        Carbon::setTestNow(); // Reset
    }

    // -------------------------------------------------------------------------
    // 8. UNDERLYING INACTIVE PANEL PRESERVED
    // -------------------------------------------------------------------------

    #[Test]
    public function test_underlying_inactive_panel_is_not_reactivated_on_expiration_or_reversal(): void
    {
        $admin = $this->createUser('admin_underlying@test.com', true);
        $reporter = $this->createUser('reporter_underlying@test.com');
        [$panel, $panelUser] = $this->createJuryPanel('Panel Inactive', 'panel_inact@test.com', 'inactive');

        $report = $this->createValidReport($reporter, $panelUser);
        $res = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'temporary',
                'duration_days' => 2,
                'reason' => 'Temporary penalty on inactive panel.',
            ],
            $this->authHeaders($admin)
        );
        $res->assertStatus(200);
        $penaltyId = $res->json('data.penalty_id');

        // Reverse the penalty
        $this->postJson(
            "/api/admin/internal-reports/penalties/{$penaltyId}/reverse",
            ['reversal_reason' => 'Premature deactivation overturned.'],
            $this->authHeaders($admin)
        )->assertStatus(200);

        // Disciplinary deactivation is gone
        $disciplineService = app(AccountJuryPanelDisciplineService::class);
        $this->assertFalse($disciplineService->hasActiveDeactivation($panelUser));

        // BUT authentic underlying status remains Inactive
        $panel->refresh();
        $this->assertEquals(TribunalJuryPanelStatus::Inactive, $panel->status);
    }

    // -------------------------------------------------------------------------
    // 9. REVERSAL RESTORES ELIGIBILITY & WRITES AUDIT
    // -------------------------------------------------------------------------

    #[Test]
    public function test_reversing_penalty_restores_eligibility_and_writes_audit(): void
    {
        $admin = $this->createUser('admin_rev@test.com', true);
        $reporter = $this->createUser('reporter_rev@test.com');
        [$panel, $panelUser] = $this->createJuryPanel('Panel Rev Test', 'panel_rev@test.com');

        $report = $this->createValidReport($reporter, $panelUser);
        $res = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'permanent',
                'reason' => 'Permanent deactivation to be reversed.',
            ],
            $this->authHeaders($admin)
        );
        $res->assertStatus(200);
        $penaltyId = $res->json('data.penalty_id');

        // Reverse
        $reverseRes = $this->postJson(
            "/api/admin/internal-reports/penalties/{$penaltyId}/reverse",
            ['reversal_reason' => 'Misconduct findings cleared upon administrative appeal.'],
            $this->authHeaders($admin)
        );
        $reverseRes->assertStatus(200);

        // Audit log created
        $this->assertDatabaseHas('internal_report_audits', [
            'internal_report_id' => $report->id,
            'action' => 'Penalty Reversed: Jury Panel Deactivation',
            'performed_by' => $admin->id,
        ]);

        // Penalty marked reversed
        $this->assertDatabaseHas('internal_penalties', [
            'id' => $penaltyId,
            'reversed_by' => $admin->id,
            'reversal_reason' => 'Misconduct findings cleared upon administrative appeal.',
        ]);

        // Dynamic restriction ceases
        $disciplineService = app(AccountJuryPanelDisciplineService::class);
        $this->assertFalse($disciplineService->hasActiveDeactivation($panelUser));
        $this->assertFalse($disciplineService->hasActiveDeactivation($panel));

        // Panel portal access works again
        $resMe = $this->getJson('/api/jury/me', $this->authHeaders($panelUser));
        $resMe->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // 10. SANITIZED NOTIFICATION AND TRANSACTION SAFETY
    // -------------------------------------------------------------------------

    #[Test]
    public function test_sanitized_notification_dispatch_and_transaction_safety(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin_notif@test.com', true);
        $reporter = $this->createUser('reporter_notif@test.com');
        [$panel, $panelUser] = $this->createJuryPanel('Panel Notif Test', 'panel_notif@test.com');

        $report = $this->createValidReport($reporter, $panelUser);

        // Normal successful application sends exactly one notification after commit
        $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::JuryPanelDeactivation->value,
                'restriction_duration_type' => 'temporary',
                'duration_days' => 20,
                'reason' => 'Confidential investigation text that should not leak.',
                'notes' => 'Internal secret notes.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        Notification::assertSentTo($panelUser, ReportedJuryPanelDeactivationNotification::class, function ($notif) use ($panelUser, $report) {
            $data = $notif->toDatabase($panelUser);

            // Message accurately states that new assignments AND operations are blocked
            $this->assertStringContainsString('cannot receive new case assignments or perform Jury Panel operations', $data['message']);

            // Zero privacy leaks
            $this->assertArrayNotHasKey('reporter_id', $data);
            $this->assertArrayNotHasKey('report_id', $data);
            $this->assertArrayNotHasKey('report_number', $data);
            $this->assertStringNotContainsString('Confidential investigation text', $data['message']);
            $this->assertStringNotContainsString('Internal secret notes', $data['message']);

            return true;
        });

        // Transaction rollback test: no notification on rollback
        Notification::fake();
        [$panel2, $panelUser2] = $this->createJuryPanel('Panel Rollback', 'panel_roll@test.com');
        $report2 = $this->createValidReport($reporter, $panelUser2);

        try {
            DB::transaction(function () use ($admin, $report2) {
                app(InternalPenaltyService::class)->applyPenalty(
                    $report2,
                    InternalPenaltyType::JuryPanelDeactivation->value,
                    'Rollback test reason',
                    null,
                    $admin,
                    7,
                    null,
                    'temporary'
                );

                throw new \Exception('Intentional rollback');
            });
        } catch (\Exception $e) {
            // Expected
        }

        Notification::assertNotSentTo($panelUser2, ReportedJuryPanelDeactivationNotification::class);
    }
}
