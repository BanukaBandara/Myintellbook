<?php

namespace Tests\Feature\InternalTribunal;

use App\Enums\InternalReportCategory;
use App\Enums\InternalReportStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\InternalReport;
use App\Models\Profile;
use App\Models\TribunalJuryPanel;
use App\Models\User;
use App\Models\profession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalReportTest extends TestCase
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
    public function authenticated_user_can_submit_internal_report_with_evidence(): void
    {
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');

        $file = UploadedFile::fake()->create('proof.pdf', 500, 'application/pdf');

        $response = $this->postJson('/api/internal-reports', [
            'reported_user_id' => $reported->id,
            'category' => InternalReportCategory::IdentityAndProfileFraud->value,
            'subject' => 'Fake Profile Picture Impersonation',
            'description' => 'This user is using an identity photo belonging to someone else.',
            'severity' => 'high',
            'evidence' => [$file],
        ], $this->authHeaders($reporter));

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.category', InternalReportCategory::IdentityAndProfileFraud->value);
        $response->assertJsonPath('data.status', InternalReportStatus::Submitted->value);

        $reportNumber = $response->json('data.report_number');
        $this->assertNotNull($reportNumber);
        $this->assertMatchesRegularExpression('/^IR-\d{4}-\d{6}$/', $reportNumber);

        $this->assertDatabaseHas('internal_reports', [
            'reporter_user_id' => $reporter->id,
            'reported_user_id' => $reported->id,
            'status' => 'Submitted',
            'report_number' => $reportNumber,
        ]);

        $this->assertDatabaseHas('internal_report_evidence', [
            'uploaded_by' => $reporter->id,
            'original_name' => 'proof.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->assertDatabaseHas('internal_report_audits', [
            'action' => 'Report Created',
            'performed_by' => $reporter->id,
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_submit_report(): void
    {
        $reported = $this->createUser('reported@example.com');

        $response = $this->postJson('/api/internal-reports', [
            'reported_user_id' => $reported->id,
            'category' => InternalReportCategory::HarassmentInappropriateBehaviour->value,
            'subject' => 'Harassment in comments',
            'description' => 'Harassing comments were sent to my profile.',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }

    #[Test]
    public function user_cannot_report_themselves(): void
    {
        $user = $this->createUser('user@example.com');

        $response = $this->postJson('/api/internal-reports', [
            'reported_user_id' => $user->id,
            'category' => InternalReportCategory::HarassmentInappropriateBehaviour->value,
            'subject' => 'Test',
            'description' => 'Testing self report rejection.',
        ], $this->authHeaders($user));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reported_user_id']);
    }

    #[Test]
    public function user_cannot_report_super_admin(): void
    {
        $reporter = $this->createUser('reporter@example.com');
        $admin = $this->createUser('admin@example.com', isAdmin: true);

        $response = $this->postJson('/api/internal-reports', [
            'reported_user_id' => $admin->id,
            'category' => InternalReportCategory::HarassmentInappropriateBehaviour->value,
            'subject' => 'Test Admin Report',
            'description' => 'Attempting to report super admin.',
        ], $this->authHeaders($reporter));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reported_user_id']);
    }

    #[Test]
    public function reporting_jury_panel_is_restricted_by_category(): void
    {
        $reporter = $this->createUser('reporter@example.com');
        $juryUser = $this->createUser('jury@example.com');

        TribunalJuryPanel::create([
            'login_user_id' => $juryUser->id,
            'panel_code' => 'JP-TEST-001',
            'panel_name' => 'Alpha Panel',
            'contact_email' => 'jury@example.com',
            'status' => 'active',
            'created_by' => $reporter->id,
        ]);

        // Wrong category for Jury Panel: rejected
        $response1 = $this->postJson('/api/internal-reports', [
            'reported_user_id' => $juryUser->id,
            'category' => InternalReportCategory::HarassmentInappropriateBehaviour->value,
            'subject' => 'Jury Misconduct',
            'description' => 'Panel misconduct description.',
        ], $this->authHeaders($reporter));

        $response1->assertStatus(422);
        $response1->assertJsonValidationErrors(['reported_user_id']);

        // Allowed category: Tribunal / Legal Process Misconduct
        $response2 = $this->postJson('/api/internal-reports', [
            'reported_user_id' => $juryUser->id,
            'category' => InternalReportCategory::TribunalLegalProcessMisconduct->value,
            'subject' => 'Jury Misconduct in deliberation',
            'description' => 'Panel misconduct description valid category.',
        ], $this->authHeaders($reporter));

        $response2->assertStatus(201);
    }

    #[Test]
    public function duplicate_active_report_for_same_user_and_category_is_prevented(): void
    {
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');

        // First report
        $res1 = $this->postJson('/api/internal-reports', [
            'reported_user_id' => $reported->id,
            'category' => InternalReportCategory::ScamSecurityPrivacyViolation->value,
            'subject' => 'First Scam Report',
            'description' => 'Sent phishing links in message.',
        ], $this->authHeaders($reporter));
        $res1->assertStatus(201);

        // Duplicate report while first is still active: rejected
        $res2 = $this->postJson('/api/internal-reports', [
            'reported_user_id' => $reported->id,
            'category' => InternalReportCategory::ScamSecurityPrivacyViolation->value,
            'subject' => 'Duplicate Scam Report',
            'description' => 'Sent phishing links again.',
        ], $this->authHeaders($reporter));

        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['reported_user_id']);
    }

    #[Test]
    public function invalid_mime_type_is_rejected(): void
    {
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');

        $file = UploadedFile::fake()->create('malicious.exe', 500, 'application/x-msdownload');

        $response = $this->postJson('/api/internal-reports', [
            'reported_user_id' => $reported->id,
            'category' => InternalReportCategory::IdentityAndProfileFraud->value,
            'subject' => 'Testing file type',
            'description' => 'Invalid file test description here.',
            'evidence' => [$file],
        ], $this->authHeaders($reporter));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['evidence.0']);
    }

    #[Test]
    public function reporter_can_only_view_own_reports_and_reported_user_cannot_view_them(): void
    {
        $reporter = $this->createUser('reporter@example.com');
        $reported = $this->createUser('reported@example.com');
        $otherUser = $this->createUser('other@example.com');

        $createRes = $this->postJson('/api/internal-reports', [
            'reported_user_id' => $reported->id,
            'category' => InternalReportCategory::AcademicScoreManipulation->value,
            'subject' => 'Exam fraud',
            'description' => 'Shared exam answers publicly.',
        ], $this->authHeaders($reporter));

        $reportId = $createRes->json('data.id');

        // Reporter can view:
        $respReporter = $this->getJson("/api/internal-reports/{$reportId}", $this->authHeaders($reporter));
        $respReporter->assertStatus(200);
        $respReporter->assertJsonPath('data.id', $reportId);

        // Reporter index contains the report:
        $respList = $this->getJson('/api/internal-reports', $this->authHeaders($reporter));
        $respList->assertStatus(200);
        $respList->assertJsonCount(1, 'data');

        // Reported user CANNOT view report:
        $respReported = $this->getJson("/api/internal-reports/{$reportId}", $this->authHeaders($reported));
        $respReported->assertStatus(403);

        // Unrelated user CANNOT view report (IDOR protection):
        $respOther = $this->getJson("/api/internal-reports/{$reportId}", $this->authHeaders($otherUser));
        $respOther->assertStatus(403);
    }

    #[Test]
    public function safe_user_search_excludes_self_and_super_admin(): void
    {
        $currentUser = $this->createUser('me@example.com');
        $targetUser = $this->createUser('target@example.com');
        $adminUser = $this->createUser('admin@example.com', isAdmin: true);

        // Search requires min 3 chars
        $resShort = $this->getJson('/api/internal-reports/users/search?q=ta', $this->authHeaders($currentUser));
        $resShort->assertStatus(200);
        $this->assertCount(0, $resShort->json('data'));

        // Search returns target user by name
        $targetName = $targetUser->profile->first_name;
        $resTarget = $this->getJson('/api/internal-reports/users/search?q='.urlencode($targetName), $this->authHeaders($currentUser));
        $resTarget->assertStatus(200);
        $this->assertCount(1, $resTarget->json('data'));
        $this->assertEquals($targetUser->id, $resTarget->json('data.0.id'));

        // Email addresses are not searchable: that would reveal who owns an address
        $resEmail = $this->getJson('/api/internal-reports/users/search?q=target%40example.com', $this->authHeaders($currentUser));
        $resEmail->assertStatus(200);
        $this->assertCount(0, $resEmail->json('data'));

        // Search does NOT return admin
        $resAdmin = $this->getJson('/api/internal-reports/users/search?q=admin', $this->authHeaders($currentUser));
        $resAdmin->assertStatus(200);
        $this->assertCount(0, $resAdmin->json('data'));
    }
}
