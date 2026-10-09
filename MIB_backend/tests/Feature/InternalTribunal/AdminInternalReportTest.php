<?php

namespace Tests\Feature\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Enums\InternalReportCategory;
use App\Enums\InternalReportStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\InternalReport;
use App\Models\Profile;
use App\Models\TribunalJuryPanel;
use App\Models\User;
use App\Models\profession;
use App\Notifications\InternalTribunal\ReportedUserSanitizedActionNotification;
use App\Notifications\InternalTribunal\ReporterStatusUpdateNotification;
use App\Services\InternalTribunal\InternalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminInternalReportTest extends TestCase
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

    protected function authHeaders(User $user): array
    {
        $rawToken = 'tok_' . Str::random(40);

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

    #[Test]
    public function normal_user_and_jury_panel_are_forbidden_from_super_admin_endpoints(): void
    {
        $user = $this->createUser('user@example.com');
        $juryUser = $this->createUser('jury@example.com');

        TribunalJuryPanel::create([
            'login_user_id' => $juryUser->id,
            'panel_code' => 'JP-TEST-002',
            'panel_name' => 'Beta Panel',
            'contact_email' => 'jury2@example.com',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $resUser = $this->getJson('/api/admin/internal-reports', $this->authHeaders($user));
        $resUser->assertStatus(403);

        $resJury = $this->getJson('/api/admin/internal-reports', $this->authHeaders($juryUser));
        $resJury->assertStatus(403);
    }

    #[Test]
    public function super_admin_can_list_and_view_internal_reports(): void
    {
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');

        $reportService = app(InternalReportService::class);
        $report = $reportService->createReport([
            'reported_user_id' => $reported->id,
            'category' => InternalReportCategory::IdentityAndProfileFraud->value,
            'subject' => 'Fake credentials in bio',
            'description' => 'User claimed false credentials in biography.',
            'severity' => 'medium',
        ], [], $reporter);

        // List
        $resList = $this->getJson('/api/admin/internal-reports', $this->authHeaders($admin));
        $resList->assertStatus(200);
        $resList->assertJsonCount(1, 'data');
        $resList->assertJsonPath('data.0.report_number', $report->report_number);

        // Details
        $resShow = $this->getJson("/api/admin/internal-reports/{$report->id}", $this->authHeaders($admin));
        $resShow->assertStatus(200);
        $resShow->assertJsonPath('data.report_number', $report->report_number);
        $resShow->assertJsonPath('data.reporter.id', $reporter->id);
        $resShow->assertJsonPath('data.reported_user.id', $reported->id);
    }

    #[Test]
    public function super_admin_can_update_status_and_reporter_is_notified(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');

        $reportService = app(InternalReportService::class);
        $report = $reportService->createReport([
            'reported_user_id' => $reported->id,
            'category' => InternalReportCategory::HarassmentInappropriateBehaviour->value,
            'subject' => 'Inappropriate comments',
            'description' => 'User posted offensive language.',
        ], [], $reporter);

        $response = $this->patchJson("/api/admin/internal-reports/{$report->id}/status", [
            'status' => InternalReportStatus::UnderReview->value,
            'notes' => 'Assigned to investigation queue.',
        ], $this->authHeaders($admin));

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'UnderReview');
        $response->assertJsonPath('data.admin_notes', 'Assigned to investigation queue.');

        $this->assertDatabaseHas('internal_reports', [
            'id' => $report->id,
            'status' => 'UnderReview',
            'reviewed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('internal_report_reviews', [
            'internal_report_id' => $report->id,
            'reviewed_by' => $admin->id,
            'from_status' => 'Submitted',
            'to_status' => 'UnderReview',
        ]);

        $this->assertDatabaseHas('internal_report_audits', [
            'internal_report_id' => $report->id,
            'action' => 'Status Updated: Submitted -> UnderReview',
            'performed_by' => $admin->id,
        ]);

        Notification::assertSentTo($reporter, ReporterStatusUpdateNotification::class);
    }

    #[Test]
    public function super_admin_can_apply_safe_actions_and_reported_user_receives_sanitized_notice(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');

        $reportService = app(InternalReportService::class);
        $report = $reportService->createReport([
            'reported_user_id' => $reported->id,
            'category' => InternalReportCategory::IdentityAndProfileFraud->value,
            'subject' => 'Fake profile image',
            'description' => 'Using celebrity photo without authorization.',
        ], [], $reporter);

        // Test Warning action
        $resWarning = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::FormalWarning->value,
            'reason' => 'Celebrity profile photo violates impersonation policies.',
            'notes' => 'Second warning on file.',
        ], $this->authHeaders($admin));

        $resWarning->assertStatus(200);
        $resWarning->assertJsonPath('status', true);
        $resWarning->assertJsonPath('data.action_type', InternalPenaltyType::FormalWarning->value);

        $this->assertDatabaseHas('internal_penalties', [
            'internal_report_id' => $report->id,
            'user_id' => $reported->id,
            'action_type' => InternalPenaltyType::FormalWarning->value,
            'applied_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('internal_report_audits', [
            'internal_report_id' => $report->id,
            'action' => 'Penalty Applied: Formal Warning',
            'performed_by' => $admin->id,
        ]);

        // Reported user gets sanitized notification
        Notification::assertSentTo($reported, ReportedUserSanitizedActionNotification::class, function ($notification) use ($reporter) {
            $data = $notification->toDatabase(new \stdClass());
            // Must NOT reveal reporter identity or report ID
            $this->assertStringNotContainsString($reporter->email, json_encode($data));
            $this->assertArrayNotHasKey('reporter_id', $data);
            $this->assertEquals(InternalPenaltyType::FormalWarning->value, $data['action_type']);
            return true;
        });

        // Test Profile Correction Required
        $resCorrection = $this->postJson("/api/admin/internal-reports/{$report->id}/penalties", [
            'action_type' => InternalPenaltyType::ProfileCorrectionRequired->value,
            'reason' => 'Please remove copyrighted photo and replace with valid profile image.',
        ], $this->authHeaders($admin));

        $resCorrection->assertStatus(200);
        $this->assertDatabaseHas('internal_penalties', [
            'internal_report_id' => $report->id,
            'user_id' => $reported->id,
            'action_type' => InternalPenaltyType::ProfileCorrectionRequired->value,
        ]);
    }

    #[Test]
    public function evidence_download_security(): void
    {
        $admin = $this->createUser('admin@example.com', isAdmin: true);
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');
        $unrelated = $this->createUser('unrelated@example.com');

        $file = UploadedFile::fake()->create('contract.pdf', 300, 'application/pdf');

        $reportService = app(InternalReportService::class);
        $report = $reportService->createReport([
            'reported_user_id' => $reported->id,
            'category' => InternalReportCategory::QualificationProfessionalFraud->value,
            'subject' => 'Forged diploma',
            'description' => 'Document attached demonstrates forged seal.',
        ], [$file], $reporter);

        $evidence = $report->evidence->first();
        $this->assertNotNull($evidence);

        // Reporter CAN download:
        $resReporter = $this->get("/api/internal-reports/evidence/{$evidence->id}/download", $this->authHeaders($reporter));
        $resReporter->assertStatus(200);

        // Admin CAN download:
        $resAdmin = $this->get("/api/admin/internal-reports/evidence/{$evidence->id}/download", $this->authHeaders($admin));
        $resAdmin->assertStatus(200);

        // Reported user CANNOT download (confidential whistleblower protection):
        $resReported = $this->get("/api/internal-reports/evidence/{$evidence->id}/download", $this->authHeaders($reported));
        $resReported->assertStatus(403);

        // Unrelated user CANNOT download:
        $resUnrelated = $this->get("/api/internal-reports/evidence/{$evidence->id}/download", $this->authHeaders($unrelated));
        $resUnrelated->assertStatus(403);
    }
}
