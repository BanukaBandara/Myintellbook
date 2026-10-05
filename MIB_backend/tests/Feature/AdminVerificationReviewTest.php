<?php

namespace Tests\Feature;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalAdjudicatorStatus;
use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalPartyRole;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\ProfessionalVerification;
use App\Models\profession;
use App\Models\Profile;
use App\Models\TribunalCase;
use App\Models\User;
use App\Notifications\Professional\ProfessionalVerificationApprovedNotification;
use App\Notifications\Professional\ProfessionalVerificationRejectedNotification;
use App\Notifications\Professional\ProfessionalVerificationSuspendedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminVerificationReviewTest extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        $cat = Category::create(['name' => 'Legal Admin Test']);
        $prof = profession::create([
            'category_id' => $cat->id,
            'name' => 'Lawyer',
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
            'first_name' => "AdminFirst_{$suffix}",
            'last_name' => "AdminLast_{$suffix}",
            'gender' => 1,
            'profession_id' => $this->professionId,
            'birth_date' => '1985-05-15',
        ]);

        return $user;
    }

    protected function authHeaders(User $user): array
    {
        $rawToken = 'adm_token_' . Str::random(40);

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

    protected function createPendingVerification(User $applicant): ProfessionalVerification
    {
        return ProfessionalVerification::create([
            'user_id' => $applicant->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Pending,
            'registration_number' => 'BAR-TEST-1234',
            'enrollment_number' => 'ENR-TEST-5678',
            'issuing_authority' => 'Bar Council of Legal Affairs',
            'years_of_experience' => 5,
            'submitted_at' => now(),
        ]);
    }

    #[Test]
    public function unauthenticated_user_receives_401_from_admin_routes(): void
    {
        $response = $this->getJson('/api/admin/professional-verifications');
        $response->assertStatus(401);
    }

    #[Test]
    public function normal_user_with_is_admin_zero_receives_403_from_all_admin_review_routes(): void
    {
        $normalUser = $this->createUser('normal.user@example.com', isAdmin: false);
        $applicant = $this->createUser('applicant.test@example.com', isAdmin: false);
        $pv = $this->createPendingVerification($applicant);

        $headers = $this->authHeaders($normalUser);

        // 1. List
        $this->withHeaders($headers)
            ->getJson('/api/admin/professional-verifications')
            ->assertStatus(403);

        // 2. Show
        $this->withHeaders($headers)
            ->getJson("/api/admin/professional-verifications/{$pv->id}")
            ->assertStatus(403);

        // 3. Approve
        $this->withHeaders($headers)
            ->postJson("/api/admin/professional-verifications/{$pv->id}/approve")
            ->assertStatus(403);

        // 4. Reject
        $this->withHeaders($headers)
            ->postJson("/api/admin/professional-verifications/{$pv->id}/reject", [
                'rejection_reason' => 'Should fail due to unauthorized role.',
            ])
            ->assertStatus(403);

        // 5. Suspend
        $this->withHeaders($headers)
            ->postJson("/api/admin/professional-verifications/{$pv->id}/suspend", [
                'suspension_reason' => 'Should fail due to unauthorized role.',
            ])
            ->assertStatus(403);

        // 6. Download document
        $this->withHeaders($headers)
            ->getJson("/api/admin/professional-verifications/{$pv->id}/documents/qualification")
            ->assertStatus(403);
    }

    #[Test]
    public function admin_with_is_admin_one_receives_200_from_admin_verification_listing(): void
    {
        $admin = $this->createUser('admin.reviewer@example.com', isAdmin: true);
        $applicant = $this->createUser('applicant.sub@example.com', isAdmin: false);
        $this->createPendingVerification($applicant);

        $response = $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/admin/professional-verifications');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'user_id',
                    'profession_type',
                    'profession_label',
                    'verification_status',
                    'masked_registration_number',
                    'issuing_authority',
                    'submitted_at',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'total'],
        ]);
        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function admin_can_view_single_application_details(): void
    {
        $admin = $this->createUser('admin.show@example.com', isAdmin: true);
        $applicant = $this->createUser('applicant.single@example.com', isAdmin: false);
        $pv = $this->createPendingVerification($applicant);

        $response = $this->withHeaders($this->authHeaders($admin))
            ->getJson("/api/admin/professional-verifications/{$pv->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $pv->id);
        $response->assertJsonPath('data.user_id', $applicant->id);
        $response->assertJsonPath('data.verification_status', 'pending');
    }

    #[Test]
    public function admin_can_approve_application(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin.approver@example.com', isAdmin: true);
        $applicant = $this->createUser('applicant.to.approve@example.com', isAdmin: false);
        $pv = $this->createPendingVerification($applicant);

        $response = $this->withHeaders($this->authHeaders($admin))
            ->postJson("/api/admin/professional-verifications/{$pv->id}/approve");

        $response->assertStatus(200);
        $response->assertJsonPath('data.verification_status', 'verified');
        $response->assertJsonPath('data.is_verified', true);

        $freshPv = $pv->fresh();
        $this->assertEquals(ProfessionalVerificationStatus::Verified, $freshPv->verification_status);
        $this->assertEquals($admin->id, $freshPv->verified_by);
        $this->assertNotNull($freshPv->verified_at);

        // Adjudicator candidate profile auto-provisioned
        $this->assertDatabaseHas('tribunal_adjudicator_profiles', [
            'user_id' => $applicant->id,
            'professional_verification_id' => $pv->id,
            'status' => TribunalAdjudicatorStatus::Pending->value,
        ]);

        Notification::assertSentTo($applicant, ProfessionalVerificationApprovedNotification::class);
    }

    #[Test]
    public function normal_user_cannot_approve_application(): void
    {
        $normalUser = $this->createUser('normal.attacker@example.com', isAdmin: false);
        $applicant = $this->createUser('applicant.target@example.com', isAdmin: false);
        $pv = $this->createPendingVerification($applicant);

        $response = $this->withHeaders($this->authHeaders($normalUser))
            ->postJson("/api/admin/professional-verifications/{$pv->id}/approve");

        $response->assertStatus(403);
        $this->assertEquals(ProfessionalVerificationStatus::Pending, $pv->fresh()->verification_status);
    }

    #[Test]
    public function applicant_cannot_approve_themselves(): void
    {
        $applicant = $this->createUser('applicant.self@example.com', isAdmin: false);
        $pv = $this->createPendingVerification($applicant);

        // Applicant attempts to approve own verification
        $response = $this->withHeaders($this->authHeaders($applicant))
            ->postJson("/api/admin/professional-verifications/{$pv->id}/approve");

        $response->assertStatus(403);
        $this->assertEquals(ProfessionalVerificationStatus::Pending, $pv->fresh()->verification_status);
    }

    #[Test]
    public function admin_can_reject_with_reason(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin.rejector@example.com', isAdmin: true);
        $applicant = $this->createUser('applicant.rejectable@example.com', isAdmin: false);
        $pv = $this->createPendingVerification($applicant);

        $reason = 'The bar enrollment certificate provided could not be authenticated with the state council.';

        $response = $this->withHeaders($this->authHeaders($admin))
            ->postJson("/api/admin/professional-verifications/{$pv->id}/reject", [
                'rejection_reason' => $reason,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.verification_status', 'rejected');

        $freshPv = $pv->fresh();
        $this->assertEquals(ProfessionalVerificationStatus::Rejected, $freshPv->verification_status);
        $this->assertEquals($reason, $freshPv->rejection_reason);
        $this->assertEquals($admin->id, $freshPv->verified_by);

        Notification::assertSentTo($applicant, ProfessionalVerificationRejectedNotification::class);
    }

    #[Test]
    public function rejection_requires_valid_rejection_reason(): void
    {
        $admin = $this->createUser('admin.reject_validation@example.com', isAdmin: true);
        $applicant = $this->createUser('applicant.novalid@example.com', isAdmin: false);
        $pv = $this->createPendingVerification($applicant);

        // Missing reason -> 422
        $response = $this->withHeaders($this->authHeaders($admin))
            ->postJson("/api/admin/professional-verifications/{$pv->id}/reject", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rejection_reason']);

        // Too short reason (< 10 chars) -> 422
        $responseShort = $this->withHeaders($this->authHeaders($admin))
            ->postJson("/api/admin/professional-verifications/{$pv->id}/reject", [
                'rejection_reason' => 'bad',
            ]);

        $responseShort->assertStatus(422);
        $responseShort->assertJsonValidationErrors(['rejection_reason']);
    }

    #[Test]
    public function admin_can_suspend_with_reason(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin.suspender@example.com', isAdmin: true);
        $applicant = $this->createUser('applicant.suspendable@example.com', isAdmin: false);

        $pv = ProfessionalVerification::create([
            'user_id' => $applicant->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Verified,
            'registration_number' => 'BAR-SUSPEND-99',
            'issuing_authority' => 'Bar Council',
            'years_of_experience' => 10,
            'submitted_at' => now()->subMonths(2),
            'verified_at' => now()->subMonth(),
            'verified_by' => $admin->id,
        ]);

        $suspensionReason = 'Bar license temporarily suspended due to professional conduct investigation.';

        $response = $this->withHeaders($this->authHeaders($admin))
            ->postJson("/api/admin/professional-verifications/{$pv->id}/suspend", [
                'suspension_reason' => $suspensionReason,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.verification_status', 'suspended');

        $freshPv = $pv->fresh();
        $this->assertEquals(ProfessionalVerificationStatus::Suspended, $freshPv->verification_status);
        $this->assertEquals($suspensionReason, $freshPv->suspension_reason);

        Notification::assertSentTo($applicant, ProfessionalVerificationSuspendedNotification::class);
    }

    #[Test]
    public function suspension_requires_valid_suspension_reason(): void
    {
        $admin = $this->createUser('admin.suspend_val@example.com', isAdmin: true);
        $applicant = $this->createUser('applicant.suspend_val@example.com', isAdmin: false);

        $pv = ProfessionalVerification::create([
            'user_id' => $applicant->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Verified,
            'registration_number' => 'BAR-SUSPEND-VAL',
            'issuing_authority' => 'Bar Council',
            'submitted_at' => now(),
        ]);

        // Missing reason -> 422
        $response = $this->withHeaders($this->authHeaders($admin))
            ->postJson("/api/admin/professional-verifications/{$pv->id}/suspend", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['suspension_reason']);

        // Short reason -> 422
        $responseShort = $this->withHeaders($this->authHeaders($admin))
            ->postJson("/api/admin/professional-verifications/{$pv->id}/suspend", [
                'suspension_reason' => 'short',
            ]);

        $responseShort->assertStatus(422);
        $responseShort->assertJsonValidationErrors(['suspension_reason']);
    }

    #[Test]
    public function private_documents_cannot_be_accessed_by_unrelated_users(): void
    {
        Storage::fake('local');

        $applicant = $this->createUser('applicant.docstest@example.com', isAdmin: false);
        $unrelatedUser = $this->createUser('unrelated.docstest@example.com', isAdmin: false);
        $admin = $this->createUser('admin.docstest@example.com', isAdmin: true);

        $fakeDoc = UploadedFile::fake()->create('bar_license_test.pdf', 150, 'application/pdf');
        $storedPath = $fakeDoc->storeAs("professional_verifications/{$applicant->id}", 'bar_license_test.pdf', 'local');

        $pv = ProfessionalVerification::create([
            'user_id' => $applicant->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Pending,
            'issuing_authority' => 'Bar Council',
            'qualification_document_path' => $storedPath,
            'submitted_at' => now(),
        ]);

        // Unrelated normal user -> 403
        $this->withHeaders($this->authHeaders($unrelatedUser))
            ->get("/api/admin/professional-verifications/{$pv->id}/documents/qualification")
            ->assertStatus(403);

        // Admin -> 200 Stream
        $this->withHeaders($this->authHeaders($admin))
            ->get("/api/admin/professional-verifications/{$pv->id}/documents/qualification")
            ->assertStatus(200);
    }

    #[Test]
    public function existing_login_returns_is_admin_flag(): void
    {
        $adminUser = $this->createUser('login.admin@example.com', isAdmin: true);
        $normalUser = $this->createUser('login.normal@example.com', isAdmin: false);

        // Admin login
        $adminResponse = $this->postJson('/api/login', [
            'email' => 'login.admin@example.com',
            'password' => 'password123',
        ]);

        $adminResponse->assertStatus(200);
        $adminResponse->assertJsonPath('status', true);
        $adminResponse->assertJsonPath('user.is_admin', true);
        $this->assertNotEmpty($adminResponse->json('token'));

        // Normal user login
        $normalResponse = $this->postJson('/api/login', [
            'email' => 'login.normal@example.com',
            'password' => 'password123',
        ]);

        $normalResponse->assertStatus(200);
        $normalResponse->assertJsonPath('status', true);
        $normalResponse->assertJsonPath('user.is_admin', false);
    }

    #[Test]
    public function user_check_endpoint_returns_safe_user_data_with_is_admin(): void
    {
        $admin = $this->createUser('check.admin@example.com', isAdmin: true);

        $response = $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $admin->id);
        $response->assertJsonPath('data.email', 'check.admin@example.com');
        $response->assertJsonPath('data.is_admin', true);
    }

    #[Test]
    public function existing_tribunal_functionality_remains_operational(): void
    {
        $complainant = $this->createUser('comp.remains@example.com', isAdmin: false);
        $respondent = $this->createUser('resp.remains@example.com', isAdmin: false);

        // Complainant creates case
        $response = $this->withHeaders($this->authHeaders($complainant))
            ->postJson('/api/tribunal/cases', [
                'respondent_id' => $respondent->id,
                'title' => 'Contractual Non-performance',
                'category' => 'Commercial Dispute',
                'description' => 'Breach of agreement between parties regarding software deliverable.',
                'requested_resolution' => 'Refund of advance fees.',
                'severity' => 'medium',
            ]);

        $response->assertStatus(201);
        $caseId = $response->json('data.id');

        // Respondent can view case
        $showResponse = $this->withHeaders($this->authHeaders($respondent))
            ->getJson("/api/tribunal/cases/{$caseId}");

        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('data.id', $caseId);
    }
}
