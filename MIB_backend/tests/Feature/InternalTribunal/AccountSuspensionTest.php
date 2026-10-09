<?php

namespace Tests\Feature\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Enums\InternalReportCategory;
use App\Enums\InternalReportStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\InternalPenalty;
use App\Models\InternalReport;
use App\Models\Profile;
use App\Models\TribunalJuryPanel;
use App\Models\User;
use App\Models\profession;
use App\Notifications\InternalTribunal\ReportedUserSanitizedActionNotification;
use App\Services\InternalTribunal\AccountSuspensionService;
use App\Services\InternalTribunal\InternalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountSuspensionTest extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
        Storage::fake('local');

        $cat = Category::create(['name' => 'General Category']);
        $prof = profession::create([
            'category_id' => $cat->id,
            'name' => 'Member',
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
            'category' => InternalReportCategory::IdentityAndProfileFraud->value,
            'subject' => 'Misconduct detected',
            'description' => 'Detailed report description for test.',
            'severity' => 'medium',
        ], [], $reporter);

        $report->update(['status' => InternalReportStatus::Valid->value]);

        return $report;
    }

    #[Test]
    public function test_01_admin_can_apply_temporary_suspension_with_duration_and_revokes_tokens(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin@example.com', true);
        $target = $this->createUser('target@example.com');
        $reporter = $this->createUser('reporter@example.com');

        // Create an existing active token for target
        $targetHeaders = $this->authHeaders($target, $rawToken);
        $this->assertDatabaseHas('api_tokens', ['user_id' => $target->id]);

        $report = $this->createValidReport($reporter, $target);
        $adminHeaders = $this->authHeaders($admin);

        $response = $this->withHeaders($adminHeaders)->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::TemporarySuspension->value,
            'duration_days' => 14,
            'reason' => '14-day temporary suspension for violation.',
            'notes' => 'Internal note only.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data' => [
                'action_type' => InternalPenaltyType::TemporarySuspension->value,
            ],
        ]);

        // Penalty record verification
        $penalty = InternalPenalty::where('internal_report_id', $report->id)->first();
        $this->assertNotNull($penalty);
        $this->assertNotNull($penalty->starts_at);
        $this->assertNotNull($penalty->ends_at);
        $this->assertTrue(now()->diffInDays($penalty->ends_at) >= 13);

        // Tokens revoked
        $this->assertDatabaseMissing('api_tokens', ['user_id' => $target->id]);

        // Audit log created with dates
        $this->assertDatabaseHas('internal_report_audits', [
            'internal_report_id' => $report->id,
            'action' => 'Penalty Applied: Temporary Suspension',
        ]);
    }

    #[Test]
    public function test_02_admin_can_apply_permanent_suspension_with_null_ends_at(): void
    {
        $admin = $this->createUser('admin@example.com', true);
        $target = $this->createUser('target@example.com');
        $reporter = $this->createUser('reporter@example.com');

        $this->authHeaders($target);
        $report = $this->createValidReport($reporter, $target);
        $adminHeaders = $this->authHeaders($admin);

        $response = $this->withHeaders($adminHeaders)->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::PermanentSuspension->value,
            'reason' => 'Permanent suspension for fraud.',
        ]);

        $response->assertStatus(200);

        $penalty = InternalPenalty::where('internal_report_id', $report->id)->first();
        $this->assertNotNull($penalty);
        $this->assertNotNull($penalty->starts_at);
        $this->assertNull($penalty->ends_at);

        // Tokens revoked
        $this->assertDatabaseMissing('api_tokens', ['user_id' => $target->id]);
    }

    #[Test]
    public function test_03_super_admin_cannot_be_suspended_via_validation(): void
    {
        $admin1 = $this->createUser('admin1@example.com', true);
        $admin2 = $this->createUser('admin2@example.com', true);
        $reporter = $this->createUser('reporter@example.com');

        $report = $this->createValidReport($reporter, $admin2);
        $adminHeaders = $this->authHeaders($admin1);

        $response = $this->withHeaders($adminHeaders)->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::TemporarySuspension->value,
            'duration_days' => 7,
            'reason' => 'Attempting to suspend admin.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['action_type']);
    }

    #[Test]
    public function test_04_jury_panel_account_cannot_be_suspended_via_validation(): void
    {
        $admin = $this->createUser('admin@example.com', true);
        $juryUser = $this->createUser('jury@example.com');
        $reporter = $this->createUser('reporter@example.com');
        TribunalJuryPanel::create([
            'login_user_id' => $juryUser->id,
            'panel_code' => 'JP-' . Str::upper(Str::random(6)),
            'panel_name' => 'Panel Beta',
            'contact_email' => 'jury@example.com',
            'status' => 'active',
            'created_by' => $reporter->id,
        ]);

        $report = $this->createValidReport($reporter, $juryUser);
        $adminHeaders = $this->authHeaders($admin);

        $response = $this->withHeaders($adminHeaders)->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::PermanentSuspension->value,
            'reason' => 'Attempting to suspend jury panel account.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['action_type']);
    }

    #[Test]
    public function test_05_account_suspension_service_defense_in_depth_exempts_admin_and_jury(): void
    {
        $admin = $this->createUser('admin@example.com', true);
        $juryUser = $this->createUser('jury@example.com');
        $reporter = $this->createUser('reporter@example.com');

        TribunalJuryPanel::create([
            'login_user_id' => $juryUser->id,
            'panel_code' => 'JP-' . Str::upper(Str::random(6)),
            'panel_name' => 'Panel Gamma',
            'contact_email' => 'jury2@example.com',
            'status' => 'active',
            'created_by' => $reporter->id,
        ]);
        $reportAdmin = $this->createValidReport($reporter, $admin);
        $reportJury = $this->createValidReport($reporter, $juryUser);

        // Artificially insert suspension records in DB
        (new InternalPenalty())->forceFill([
            'internal_report_id' => $reportAdmin->id,
            'user_id' => $admin->id,
            'action_type' => InternalPenaltyType::PermanentSuspension->value,
            'reason' => 'Test',
            'applied_by' => $admin->id,
            'applied_at' => now(),
            'starts_at' => now(),
            'ends_at' => null,
        ])->save();

        (new InternalPenalty())->forceFill([
            'internal_report_id' => $reportJury->id,
            'user_id' => $juryUser->id,
            'action_type' => InternalPenaltyType::PermanentSuspension->value,
            'reason' => 'Test',
            'applied_by' => $admin->id,
            'applied_at' => now(),
            'starts_at' => now(),
            'ends_at' => null,
        ])->save();

        $service = app(AccountSuspensionService::class);
        $this->assertNull($service->getActiveSuspension($admin));
        $this->assertFalse($service->isUserSuspended($admin));

        $this->assertNull($service->getActiveSuspension($juryUser));
        $this->assertFalse($service->isUserSuspended($juryUser));
    }

    #[Test]
    public function test_06_revoked_old_token_receives_401_unauthorized(): void
    {
        $admin = $this->createUser('admin@example.com', true);
        $target = $this->createUser('target@example.com');
        $reporter = $this->createUser('reporter@example.com');

        $targetHeaders = $this->authHeaders($target, $rawToken);

        // Verify token works before suspension
        $preCheck = $this->withHeaders($targetHeaders)->getJson('/api/user');
        $preCheck->assertStatus(200);

        // Admin applies suspension
        $report = $this->createValidReport($reporter, $target);
        $adminHeaders = $this->authHeaders($admin);
        $this->withHeaders($adminHeaders)->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::TemporarySuspension->value,
            'duration_days' => 5,
            'reason' => 'Suspension',
        ]);

        // Old token now receives 401 because record was deleted
        $postCheck = $this->withHeaders($targetHeaders)->getJson('/api/user');
        $postCheck->assertStatus(401);
    }

    #[Test]
    public function test_07_suspended_user_fresh_login_receives_sanitized_403_without_private_details(): void
    {
        $admin = $this->createUser('admin@example.com', true);
        $target = $this->createUser('target@example.com');
        $reporter = $this->createUser('reporter@example.com');

        $report = $this->createValidReport($reporter, $target);
        $adminHeaders = $this->authHeaders($admin);

        $this->withHeaders($adminHeaders)->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::TemporarySuspension->value,
            'duration_days' => 7,
            'reason' => 'Classified secret misconduct evidence from confidential reporter.',
            'notes' => 'Internal sensitive notes.',
        ]);

        // Fresh login attempt
        $loginRes = $this->postJson('/api/login', [
            'email' => 'target@example.com',
            'password' => 'password123',
        ]);

        $loginRes->assertStatus(403);
        $loginRes->assertJson([
            'code' => 403,
            'status' => false,
            'suspended' => true,
            'suspension_type' => 'temporary',
        ]);
        $loginRes->assertJsonStructure([
            'code',
            'status',
            'message',
            'suspended',
            'suspension_type',
            'ends_at',
        ]);

        // Privacy assertion: zero internal details leaked
        $json = $loginRes->json();
        $this->assertArrayNotHasKey('reason', $json);
        $this->assertArrayNotHasKey('notes', $json);
        $this->assertArrayNotHasKey('reporter', $json);
        $this->assertArrayNotHasKey('reporter_id', $json);
        $this->assertArrayNotHasKey('report_number', $json);
        $this->assertStringNotContainsString('Classified secret', json_encode($json));
        $this->assertStringNotContainsString('Internal sensitive notes', json_encode($json));
    }

    #[Test]
    public function test_08_middleware_blocks_valid_token_if_user_is_suspended(): void
    {
        $admin = $this->createUser('admin@example.com', true);
        $target = $this->createUser('target@example.com');
        $reporter = $this->createUser('reporter@example.com');

        $report = $this->createValidReport($reporter, $target);

        // Artificially create an active suspension record
        (new InternalPenalty())->forceFill([
            'internal_report_id' => $report->id,
            'user_id' => $target->id,
            'action_type' => InternalPenaltyType::PermanentSuspension->value,
            'reason' => 'Permanent suspension',
            'applied_by' => $admin->id,
            'applied_at' => now(),
            'starts_at' => now(),
            'ends_at' => null,
        ])->save();

        // Artificially create a valid token for target
        $targetHeaders = $this->authHeaders($target);

        // Middleware should block with sanitized 403
        $response = $this->withHeaders($targetHeaders)->getJson('/api/user');
        $response->assertStatus(403);
        $response->assertJson([
            'code' => 403,
            'status' => false,
            'suspended' => true,
            'suspension_type' => 'permanent',
        ]);
    }

    #[Test]
    public function test_09_unrelated_user_is_unaffected_by_suspension(): void
    {
        $admin = $this->createUser('admin@example.com', true);
        $target = $this->createUser('target@example.com');
        $innocent = $this->createUser('innocent@example.com');
        $reporter = $this->createUser('reporter@example.com');

        $report = $this->createValidReport($reporter, $target);
        $adminHeaders = $this->authHeaders($admin);

        $this->withHeaders($adminHeaders)->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::PermanentSuspension->value,
            'reason' => 'Target is suspended.',
        ]);

        // Innocent user logs in successfully
        $loginRes = $this->postJson('/api/login', [
            'email' => 'innocent@example.com',
            'password' => 'password123',
        ]);
        $loginRes->assertStatus(200);
        $loginRes->assertJson(['status' => true]);

        // Innocent user can access protected endpoints
        $innocentHeaders = $this->authHeaders($innocent);
        $profileRes = $this->withHeaders($innocentHeaders)->getJson('/api/user');
        $profileRes->assertStatus(200);
    }

    #[Test]
    public function test_10_future_starts_at_suspension_is_not_yet_active(): void
    {
        $admin = $this->createUser('admin@example.com', true);
        $target = $this->createUser('target@example.com');
        $reporter = $this->createValidReport($admin, $target);

        // Create penalty starting tomorrow
        (new InternalPenalty())->forceFill([
            'internal_report_id' => $reporter->id,
            'user_id' => $target->id,
            'action_type' => InternalPenaltyType::TemporarySuspension->value,
            'reason' => 'Future suspension',
            'applied_by' => $admin->id,
            'applied_at' => now(),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(8),
        ])->save();

        $service = app(AccountSuspensionService::class);
        $this->assertNull($service->getActiveSuspension($target));
        $this->assertFalse($service->isUserSuspended($target));

        // Target can still log in today
        $loginRes = $this->postJson('/api/login', [
            'email' => 'target@example.com',
            'password' => 'password123',
        ]);
        $loginRes->assertStatus(200);
    }

    #[Test]
    public function test_11_temporary_suspension_automatically_expires_after_ends_at(): void
    {
        $admin = $this->createUser('admin@example.com', true);
        $target = $this->createUser('target@example.com');
        $reporter = $this->createUser('reporter@example.com');

        $report = $this->createValidReport($reporter, $target);
        $adminHeaders = $this->authHeaders($admin);

        $this->withHeaders($adminHeaders)->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::TemporarySuspension->value,
            'duration_days' => 3,
            'reason' => '3-day temporary suspension.',
        ]);

        // Suspended on day 1
        $loginDay1 = $this->postJson('/api/login', [
            'email' => 'target@example.com',
            'password' => 'password123',
        ]);
        $loginDay1->assertStatus(403);

        // Travel 4 days forward
        $this->travel(4)->days();

        // Expired automatically: fresh login succeeds
        $loginDay5 = $this->postJson('/api/login', [
            'email' => 'target@example.com',
            'password' => 'password123',
        ]);
        $loginDay5->assertStatus(200);
        $loginDay5->assertJson(['status' => true]);
    }

    #[Test]
    public function test_12_reversal_immediately_allows_login_without_restoring_old_tokens(): void
    {
        $admin = $this->createUser('admin@example.com', true);
        $target = $this->createUser('target@example.com');
        $reporter = $this->createUser('reporter@example.com');

        // Create initial token
        $targetHeaders = $this->authHeaders($target);

        $report = $this->createValidReport($reporter, $target);
        $adminHeaders = $this->authHeaders($admin);

        $this->withHeaders($adminHeaders)->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::PermanentSuspension->value,
            'reason' => 'Permanent suspension.',
        ]);

        $penalty = InternalPenalty::where('internal_report_id', $report->id)->first();

        // Reverse the penalty
        $revRes = $this->withHeaders($adminHeaders)->postJson("/api/admin/internal-reports/penalties/{$penalty->id}/reverse", [
            'reversal_reason' => 'Identity verified upon administrative appeal.',
        ]);
        $revRes->assertStatus(200);

        // Old token remains dead (401)
        $oldTokenRes = $this->withHeaders($targetHeaders)->getJson('/api/user');
        $oldTokenRes->assertStatus(401);

        // Target can now log in fresh
        $loginRes = $this->postJson('/api/login', [
            'email' => 'target@example.com',
            'password' => 'password123',
        ]);
        $loginRes->assertStatus(200);
        $loginRes->assertJson(['status' => true]);

        // Audit log created for reversal
        $this->assertDatabaseHas('internal_report_audits', [
            'internal_report_id' => $report->id,
            'action' => 'Penalty Reversed: Permanent Suspension',
        ]);
    }

    #[Test]
    public function test_13_phase_1_actions_do_not_suspend_or_revoke_tokens(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin@example.com', true);
        $target = $this->createUser('target@example.com');
        $reporter = $this->createUser('reporter@example.com');

        $targetHeaders = $this->authHeaders($target);
        $report = $this->createValidReport($reporter, $target);
        $adminHeaders = $this->authHeaders($admin);

        // Apply Warning
        $res = $this->withHeaders($adminHeaders)->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::Warning->value,
            'reason' => 'First warning issued.',
        ]);
        $res->assertStatus(200);

        // Tokens still intact
        $this->assertDatabaseHas('api_tokens', ['user_id' => $target->id]);

        // Target can access protected routes
        $profRes = $this->withHeaders($targetHeaders)->getJson('/api/user');
        $profRes->assertStatus(200);

        // Target is not suspended
        $service = app(AccountSuspensionService::class);
        $this->assertFalse($service->isUserSuspended($target));
    }
}
