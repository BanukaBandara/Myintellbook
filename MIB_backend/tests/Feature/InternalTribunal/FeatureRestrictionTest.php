<?php

namespace Tests\Feature\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Enums\InternalReportCategory;
use App\Enums\InternalReportStatus;
use App\Enums\RestrictedFeature;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\InternalPenalty;
use App\Models\InternalReport;
use App\Models\Profile;
use App\Models\TribunalJuryPanel;
use App\Models\User;
use App\Models\profession;
use App\Notifications\InternalTribunal\ReportedUserSanitizedActionNotification;
use App\Services\InternalTribunal\AccountFeatureRestrictionService;
use App\Services\InternalTribunal\InternalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatureRestrictionTest extends TestCase
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
    public function super_admin_can_apply_temporary_feature_restriction(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');
        $report = $this->createValidReport($reporter, $reported);

        $headers = $this->authHeaders($admin);

        $response = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::CommunityPosting->value,
            'restriction_duration_type' => 'temporary',
            'duration_days' => 14,
            'reason' => 'User spamming notes on public wall.',
            'notes' => 'Temporary 14 day posting ban.',
        ], $headers);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data' => [
                'action_type' => InternalPenaltyType::FeatureRestriction->value,
            ],
        ]);

        $this->assertDatabaseHas('internal_penalties', [
            'internal_report_id' => $report->id,
            'user_id' => $reported->id,
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::CommunityPosting->value,
            'applied_by' => $admin->id,
        ]);

        $penalty = InternalPenalty::where('user_id', $reported->id)->first();
        $this->assertNotNull($penalty->starts_at);
        $this->assertNotNull($penalty->ends_at);
        $this->assertTrue(\Carbon\Carbon::parse($penalty->ends_at)->isFuture());

        Notification::assertSentTo($reported, ReportedUserSanitizedActionNotification::class);
    }

    #[Test]
    public function super_admin_can_apply_permanent_feature_restriction(): void
    {
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');
        $report = $this->createValidReport($reporter, $reported);

        $headers = $this->authHeaders($admin);

        $response = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::TribunalParticipation->value,
            'restriction_duration_type' => 'permanent',
            'reason' => 'User submitted forged affidavits to the Tribunal.',
            'notes' => 'Indefinite Tribunal participation restriction.',
        ], $headers);

        $response->assertStatus(200);

        $this->assertDatabaseHas('internal_penalties', [
            'internal_report_id' => $report->id,
            'user_id' => $reported->id,
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::TribunalParticipation->value,
            'ends_at' => null,
        ]);
    }

    #[Test]
    public function invalid_feature_key_is_rejected(): void
    {
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');
        $report = $this->createValidReport($reporter, $reported);

        $headers = $this->authHeaders($admin);

        $response = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => 'invalid_unknown_feature',
            'restriction_duration_type' => 'permanent',
            'reason' => 'Some test reason.',
        ], $headers);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['penalty_value']);
    }

    #[Test]
    public function missing_duration_mode_is_rejected(): void
    {
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');
        $report = $this->createValidReport($reporter, $reported);

        $headers = $this->authHeaders($admin);

        $response = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::CommunityPosting->value,
            'reason' => 'Some test reason.',
        ], $headers);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['restriction_duration_type']);
    }

    #[Test]
    public function temporary_restriction_without_duration_days_is_rejected(): void
    {
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');
        $report = $this->createValidReport($reporter, $reported);

        $headers = $this->authHeaders($admin);

        $response = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::CommunityPosting->value,
            'restriction_duration_type' => 'temporary',
            'reason' => 'Some test reason.',
        ], $headers);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['duration_days']);
    }

    #[Test]
    public function duplicate_active_restriction_on_same_feature_is_rejected(): void
    {
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');
        $report = $this->createValidReport($reporter, $reported);

        $headers = $this->authHeaders($admin);

        // Apply first restriction
        $first = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::ExamAccess->value,
            'restriction_duration_type' => 'permanent',
            'reason' => 'First exam restriction.',
        ], $headers);
        $first->assertStatus(200);

        // Try applying duplicate active restriction for exam_access
        $duplicate = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::ExamAccess->value,
            'restriction_duration_type' => 'temporary',
            'duration_days' => 10,
            'reason' => 'Duplicate attempt.',
        ], $headers);

        $duplicate->assertStatus(422);
        $duplicate->assertJsonValidationErrors(['penalty_value']);
    }

    #[Test]
    public function only_valid_reports_can_receive_feature_restrictions(): void
    {
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');

        $reportService = app(InternalReportService::class);
        $report = $reportService->createReport([
            'reported_user_id' => $reported->id,
            'category' => InternalReportCategory::IdentityAndProfileFraud->value,
            'subject' => 'Unreviewed report',
            'description' => 'Waiting for review.',
            'severity' => 'low',
        ], [], $reporter);

        // Status is still Submitted
        $headers = $this->authHeaders($admin);

        $response = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::ProfileEditing->value,
            'restriction_duration_type' => 'permanent',
            'reason' => 'Attempting penalty prematurely.',
        ], $headers);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['report']);
    }

    #[Test]
    public function super_admin_and_jury_panel_accounts_cannot_be_restricted(): void
    {
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $superAdminTarget = $this->createUser('target_admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $report = $this->createValidReport($reporter, $superAdminTarget);

        $headers = $this->authHeaders($admin);

        $responseAdmin = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::CommunityPosting->value,
            'restriction_duration_type' => 'permanent',
            'reason' => 'Trying to restrict super admin.',
        ], $headers);

        $responseAdmin->assertStatus(422);
        $responseAdmin->assertJsonValidationErrors(['action_type']);

        // Test Jury Panel immunity
        $juryUser = $this->createUser('jury@example.com');
        TribunalJuryPanel::create([
            'login_user_id' => $juryUser->id,
            'panel_code' => 'JP-' . Str::upper(Str::random(6)),
            'panel_name' => 'Panel Gamma',
            'contact_email' => 'jury2@example.com',
            'status' => 'active',
            'created_by' => $reporter->id,
        ]);
        $juryReport = $this->createValidReport($reporter, $juryUser);

        $responseJury = $this->postJson("/api/admin/internal-reports/{$juryReport->id}/penalties", [
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::CommunityPosting->value,
            'restriction_duration_type' => 'permanent',
            'reason' => 'Trying to restrict jury panel.',
        ], $headers);

        $responseJury->assertStatus(422);
        $responseJury->assertJsonValidationErrors(['action_type']);
    }

    #[Test]
    public function feature_restriction_does_not_revoke_api_tokens(): void
    {
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');

        $reportedHeaders = $this->authHeaders($reported, $reportedRawToken);
        $report = $this->createValidReport($reporter, $reported);

        $this->assertDatabaseHas('api_tokens', ['user_id' => $reported->id]);

        $adminHeaders = $this->authHeaders($admin);

        $response = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::CommunityPosting->value,
            'restriction_duration_type' => 'permanent',
            'reason' => 'Restricting community posting.',
        ], $adminHeaders);

        $response->assertStatus(200);

        // Tokens must still exist!
        $this->assertDatabaseHas('api_tokens', ['user_id' => $reported->id]);

        // User can still authenticate
        $meResponse = $this->getJson('/api/user', $reportedHeaders);
        $meResponse->assertStatus(200);
    }

    #[Test]
    public function future_starts_at_does_not_block_early(): void
    {
        $reported = $this->createUser('reported@example.com');
        $reporter = $this->createUser('reporter@example.com');
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $report = $this->createValidReport($reporter, $reported);

        // Create penalty that starts in 2 hours
        (new InternalPenalty())->forceFill([
            'internal_report_id' => $report->id,
            'user_id' => $reported->id,
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::CommunityPosting->value,
            'reason' => 'Scheduled restriction',
            'applied_by' => $admin->id,
            'applied_at' => now(),
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addDays(5),
        ])->save();

        $service = app(AccountFeatureRestrictionService::class);
        $this->assertFalse($service->isRestricted($reported, RestrictedFeature::CommunityPosting->value));
    }

    #[Test]
    public function expired_restriction_automatically_restores_access(): void
    {
        $reported = $this->createUser('reported@example.com');
        $reporter = $this->createUser('reporter@example.com');
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $report = $this->createValidReport($reporter, $reported);

        // Create penalty that expired 1 hour ago
        (new InternalPenalty())->forceFill([
            'internal_report_id' => $report->id,
            'user_id' => $reported->id,
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::CommunityPosting->value,
            'reason' => 'Past restriction',
            'applied_by' => $admin->id,
            'applied_at' => now()->subDays(2),
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subHour(),
        ])->save();

        $service = app(AccountFeatureRestrictionService::class);
        $this->assertFalse($service->isRestricted($reported, RestrictedFeature::CommunityPosting->value));
    }

    #[Test]
    public function reversal_immediately_restores_feature_access(): void
    {
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');
        $report = $this->createValidReport($reporter, $reported);

        $adminHeaders = $this->authHeaders($admin);

        // Apply restriction
        $applyRes = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::CommunityPosting->value,
            'restriction_duration_type' => 'permanent',
            'reason' => 'Applying posting restriction.',
        ], $adminHeaders);
        $applyRes->assertStatus(200);

        $penalty = InternalPenalty::where('user_id', $reported->id)->first();
        $service = app(AccountFeatureRestrictionService::class);
        $this->assertTrue($service->isRestricted($reported, RestrictedFeature::CommunityPosting->value));

        // Reverse restriction
        $reverseRes = $this->postJson("/api/admin/internal-reports/penalties/{$penalty->id}/reverse", [
            'reversal_reason' => 'User submitted formal appeal and was exonerated.',
        ], $adminHeaders);
        $reverseRes->assertStatus(200);

        $this->assertFalse($service->isRestricted($reported, RestrictedFeature::CommunityPosting->value));

        $this->assertDatabaseHas('internal_report_audits', [
            'internal_report_id' => $report->id,
            'action' => 'Penalty Reversed: Feature Restriction (community_posting)',
        ]);
    }

    #[Test]
    public function sanitized_403_response_contains_no_sensitive_report_or_whistleblower_data(): void
    {
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter_whistleblower@example.com');
        $reported = $this->createUser('reported@example.com');
        $report = $this->createValidReport($reporter, $reported);

        $adminHeaders = $this->authHeaders($admin);

        $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::CommunityPosting->value,
            'restriction_duration_type' => 'permanent',
            'reason' => 'Secret confidential investigation details here.',
            'notes' => 'Super private admin notes.',
        ], $adminHeaders);

        $reportedHeaders = $this->authHeaders($reported);

        $res = $this->postJson('/api/set-comment', ['comment' => 'Hello'], $reportedHeaders);

        $res->assertStatus(403);
        $data = $res->json();

        $this->assertEquals(403, $data['code']);
        $this->assertFalse($data['status']);
        $this->assertTrue($data['feature_restricted']);
        $this->assertEquals('community_posting', $data['feature']);

        // Check privacy preservation: NO internal details leaked
        $jsonString = json_encode($data);
        $this->assertStringNotContainsString('whistleblower', $jsonString);
        $this->assertStringNotContainsString('reporter', $jsonString);
        $this->assertStringNotContainsString('Secret confidential', $jsonString);
        $this->assertStringNotContainsString('Super private', $jsonString);
        $this->assertStringNotContainsString((string) $report->report_number, $jsonString);
    }

    #[Test]
    public function all_five_feature_areas_are_backend_enforced(): void
    {
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');
        $report = $this->createValidReport($reporter, $reported);
        $reportedHeaders = $this->authHeaders($reported);

        $service = app(AccountFeatureRestrictionService::class);

        // 1. Tribunal Participation
        (new InternalPenalty())->forceFill([
            'internal_report_id' => $report->id,
            'user_id' => $reported->id,
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::TribunalParticipation->value,
            'reason' => 'Tribunal blocked',
            'applied_by' => $admin->id,
            'applied_at' => now(),
            'starts_at' => now(),
        ])->save();
        $tribunalRes = $this->postJson('/api/tribunal/cases', [], $reportedHeaders);
        $tribunalRes->assertStatus(403);
        $tribunalRes->assertJsonFragment(['feature' => 'tribunal_participation']);

        // 2. Community Posting
        (new InternalPenalty())->forceFill([
            'internal_report_id' => $report->id,
            'user_id' => $reported->id,
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::CommunityPosting->value,
            'reason' => 'Posting blocked',
            'applied_by' => $admin->id,
            'applied_at' => now(),
            'starts_at' => now(),
        ])->save();
        $postRes = $this->postJson('/api/set-comment', ['comment' => 'Test'], $reportedHeaders);
        $postRes->assertStatus(403);
        $postRes->assertJsonFragment(['feature' => 'community_posting']);

        $noteRes = $this->postJson('/api/testament/notes', ['title' => 'Test', 'body' => 'Test'], $reportedHeaders);
        $noteRes->assertStatus(403);
        $noteRes->assertJsonFragment(['feature' => 'community_posting']);

        // 3. Daily Question Access
        (new InternalPenalty())->forceFill([
            'internal_report_id' => $report->id,
            'user_id' => $reported->id,
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::DailyQuestionAccess->value,
            'reason' => 'Daily Question blocked',
            'applied_by' => $admin->id,
            'applied_at' => now(),
            'starts_at' => now(),
        ])->save();
        $dailyRes = $this->postJson('/api/submit-daily-answer', ['answer' => 'Test'], $reportedHeaders);
        $dailyRes->assertStatus(403);
        $dailyRes->assertJsonFragment(['feature' => 'daily_question_access']);

        // 4. Exam Access
        (new InternalPenalty())->forceFill([
            'internal_report_id' => $report->id,
            'user_id' => $reported->id,
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::ExamAccess->value,
            'reason' => 'Exam blocked',
            'applied_by' => $admin->id,
            'applied_at' => now(),
            'starts_at' => now(),
        ])->save();
        $examRes = $this->postJson('/api/exam/start', [], $reportedHeaders);
        $examRes->assertStatus(403);
        $examRes->assertJsonFragment(['feature' => 'exam_access']);

        // 5. Profile Editing
        (new InternalPenalty())->forceFill([
            'internal_report_id' => $report->id,
            'user_id' => $reported->id,
            'action_type' => InternalPenaltyType::FeatureRestriction->value,
            'penalty_value' => RestrictedFeature::ProfileEditing->value,
            'reason' => 'Profile editing blocked',
            'applied_by' => $admin->id,
            'applied_at' => now(),
            'starts_at' => now(),
        ])->save();
        $profileRes = $this->postJson('/api/edit-general-info', ['first_name' => 'New'], $reportedHeaders);
        $profileRes->assertStatus(403);
        $profileRes->assertJsonFragment(['feature' => 'profile_editing']);
    }
}
