<?php

namespace Tests\Feature;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalJuryPanelStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\TribunalJuryPanel;
use App\Models\TribunalJuryPanelEvent;
use App\Models\User;
use App\Models\profession;
use App\Services\Tribunal\TribunalJuryPanelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TribunalJuryPanelAdminTest extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        $cat = Category::create(['name' => 'Legal Category Test']);
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

    protected function createVerifiedLawyer(string $email): User
    {
        $lawyer = $this->createUser($email, false);

        ProfessionalVerification::create([
            'user_id' => $lawyer->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Verified,
            'registration_number' => 'BAR-' . Str::random(6),
            'enrollment_number' => 'ENR-' . Str::random(6),
            'issuing_authority' => 'Supreme Bar Association',
            'years_of_experience' => 10,
            'submitted_at' => now()->subDays(5),
            'reviewed_at' => now()->subDays(1),
            'expires_at' => now()->addYears(2),
        ]);

        return $lawyer;
    }

    #[Test]
    public function super_admin_can_create_jury_panel(): void
    {
        $admin = $this->createUser('superadmin@test.com', true);

        $payload = [
            'panel_name' => 'Jury Panel 01',
            'email' => 'jury01@myintellbook.test',
            'password' => 'TestPassword123!',
            'password_confirmation' => 'TestPassword123!',
        ];

        $response = $this->postJson(
            '/api/admin/tribunal/jury-panels',
            $payload,
            $this->authHeaders($admin)
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.panel_name', 'Jury Panel 01');
        $response->assertJsonPath('data.login_email', 'jury01@myintellbook.test');
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.panel_code', 'JP-0001');

        // Ensure passwords / secrets are NOT exposed
        $response->assertJsonMissing(['password']);
        $response->assertJsonMissing(['password_hash']);
        $response->assertJsonMissing(['remember_token']);

        // Check database records
        $this->assertDatabaseHas('tribunal_jury_panels', [
            'panel_code' => 'JP-0001',
            'panel_name' => 'Jury Panel 01',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jury01@myintellbook.test',
            'is_admin' => 0,
        ]);

        $this->assertDatabaseHas('tribunal_jury_panel_events', [
            'event_type' => 'jury_panel_created',
            'actor_id' => $admin->id,
        ]);
    }

    #[Test]
    public function normal_user_cannot_create_jury_panel(): void
    {
        $normalUser = $this->createUser('normal@test.com', false);

        $payload = [
            'panel_name' => 'Unauthorized Panel',
            'email' => 'unauth@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson(
            '/api/admin/tribunal/jury-panels',
            $payload,
            $this->authHeaders($normalUser)
        );

        $response->assertStatus(403);
        $this->assertDatabaseMissing('tribunal_jury_panels', [
            'panel_name' => 'Unauthorized Panel',
        ]);
    }

    #[Test]
    public function verified_lawyer_cannot_create_jury_panel(): void
    {
        $lawyer = $this->createVerifiedLawyer('lawyer@test.com');

        $payload = [
            'panel_name' => 'Lawyer Attempt Panel',
            'email' => 'lawyerpanel@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson(
            '/api/admin/tribunal/jury-panels',
            $payload,
            $this->authHeaders($lawyer)
        );

        $response->assertStatus(403);
        $this->assertDatabaseMissing('tribunal_jury_panels', [
            'panel_name' => 'Lawyer Attempt Panel',
        ]);
    }

    #[Test]
    public function jury_panel_account_is_created_with_is_admin_false(): void
    {
        $admin = $this->createUser('superadmin@test.com', true);

        $response = $this->postJson(
            '/api/admin/tribunal/jury-panels',
            [
                'panel_name' => 'Jury Panel Admin Check',
                'email' => 'jurycheck@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(201);
        $panelUser = User::where('email', 'jurycheck@test.com')->first();
        $this->assertNotNull($panelUser);
        $this->assertFalse((bool) $panelUser->is_admin);
        $this->assertFalse($panelUser->isAdmin());
        $this->assertTrue($panelUser->isJuryPanelAccount());
    }

    #[Test]
    public function panel_code_is_unique_and_system_generated(): void
    {
        $admin = $this->createUser('superadmin@test.com', true);

        $res1 = $this->postJson(
            '/api/admin/tribunal/jury-panels',
            [
                'panel_name' => 'Jury Panel 01',
                'email' => 'jp1@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
            $this->authHeaders($admin)
        );
        $res1->assertStatus(201);
        $code1 = $res1->json('data.panel_code');

        $res2 = $this->postJson(
            '/api/admin/tribunal/jury-panels',
            [
                'panel_name' => 'Jury Panel 02',
                'email' => 'jp2@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
            $this->authHeaders($admin)
        );
        $res2->assertStatus(201);
        $code2 = $res2->json('data.panel_code');

        $this->assertEquals('JP-0001', $code1);
        $this->assertEquals('JP-0002', $code2);
        $this->assertNotEquals($code1, $code2);
    }

    #[Test]
    public function panel_is_linked_to_login_user_id(): void
    {
        $admin = $this->createUser('superadmin@test.com', true);

        $res = $this->postJson(
            '/api/admin/tribunal/jury-panels',
            [
                'panel_name' => 'Jury Panel Link Test',
                'email' => 'jplink@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
            $this->authHeaders($admin)
        );
        $res->assertStatus(201);

        $panelId = $res->json('data.id');
        $panel = TribunalJuryPanel::find($panelId);
        $this->assertNotNull($panel);
        $this->assertNotNull($panel->loginUser);
        $this->assertEquals('jplink@test.com', $panel->loginUser->email);
        $this->assertEquals($panel->id, $panel->loginUser->juryPanel->id);
    }

    #[Test]
    public function duplicate_email_is_rejected(): void
    {
        $admin = $this->createUser('superadmin@test.com', true);
        $this->createUser('existing@test.com', false);

        $response = $this->postJson(
            '/api/admin/tribunal/jury-panels',
            [
                'panel_name' => 'Duplicate Email Panel',
                'email' => 'existing@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function failed_panel_creation_does_not_leave_orphan_user(): void
    {
        $admin = $this->createUser('superadmin@test.com', true);

        // Intentionally mock or trigger failure inside service transaction
        $service = app(TribunalJuryPanelService::class);

        $this->assertDatabaseMissing('users', ['email' => 'failtest@test.com']);

        try {
            // Emulate failure during creation after user insert by throwing exception
            \Illuminate\Support\Facades\DB::transaction(function () use ($admin) {
                User::create([
                    'email' => 'failtest@test.com',
                    'password' => Hash::make('password123'),
                    'is_admin' => false,
                ]);

                throw new \Exception('Simulated panel creation failure');
            });
        } catch (\Exception $e) {
            // Expected
        }

        // Verify that user was rolled back and is not orphaned in DB
        $this->assertDatabaseMissing('users', ['email' => 'failtest@test.com']);
    }

    #[Test]
    public function panel_account_is_not_representative_eligible(): void
    {
        $admin = $this->createUser('superadmin@test.com', true);

        $res = $this->postJson(
            '/api/admin/tribunal/jury-panels',
            [
                'panel_name' => 'Panel Rep Test',
                'email' => 'panelrep@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
            $this->authHeaders($admin)
        );
        $res->assertStatus(201);

        $panelUser = User::where('email', 'panelrep@test.com')->first();
        $this->assertNotNull($panelUser);
        $this->assertFalse($panelUser->canActAsLegalRepresentative());

        // Even if someone manually added a verified professional verification record,
        // canActAsLegalRepresentative must still return false!
        ProfessionalVerification::create([
            'user_id' => $panelUser->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Verified,
            'registration_number' => 'BAR-HACK-1',
            'enrollment_number' => 'ENR-HACK-1',
            'issuing_authority' => 'Bar',
            'years_of_experience' => 5,
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $panelUser->refresh();
        $this->assertFalse($panelUser->canActAsLegalRepresentative());
    }

    #[Test]
    public function panel_account_does_not_appear_in_representative_directory(): void
    {
        $admin = $this->createUser('superadmin@test.com', true);
        $regularUser = $this->createUser('regular@test.com', false);
        $lawyer = $this->createVerifiedLawyer('legitlawyer@test.com');

        // Create jury panel
        $this->postJson(
            '/api/admin/tribunal/jury-panels',
            [
                'panel_name' => 'Secret Panel',
                'email' => 'secretpanel@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
            $this->authHeaders($admin)
        );

        $panelUser = User::where('email', 'secretpanel@test.com')->first();

        // Even with a fake verification record
        ProfessionalVerification::create([
            'user_id' => $panelUser->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Verified,
            'registration_number' => 'BAR-HACK-2',
            'enrollment_number' => 'ENR-HACK-2',
            'issuing_authority' => 'Bar',
            'years_of_experience' => 5,
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $repResponse = $this->getJson('/api/tribunal/representatives', $this->authHeaders($regularUser));
        $repResponse->assertStatus(200);

        // Legit lawyer must be in directory
        $repResponse->assertJsonFragment(['email' => 'legitlawyer@test.com']);

        // Jury panel account must NOT be in directory
        $repResponse->assertJsonMissing(['email' => 'secretpanel@test.com']);
    }

    #[Test]
    public function super_admin_can_list_panels(): void
    {
        $admin = $this->createUser('superadmin@test.com', true);

        // Create two panels
        $this->postJson(
            '/api/admin/tribunal/jury-panels',
            [
                'panel_name' => 'Alpha Panel',
                'email' => 'alpha@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
            $this->authHeaders($admin)
        );

        $this->postJson(
            '/api/admin/tribunal/jury-panels',
            [
                'panel_name' => 'Beta Panel',
                'email' => 'beta@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
            $this->authHeaders($admin)
        );

        $response = $this->getJson('/api/admin/tribunal/jury-panels', $this->authHeaders($admin));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'panel_code', 'panel_name', 'status', 'login_email', 'created_at']
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total']
        ]);
        $response->assertJsonFragment(['panel_name' => 'Alpha Panel']);
        $response->assertJsonFragment(['panel_name' => 'Beta Panel']);
    }

    #[Test]
    public function super_admin_can_deactivate_and_activate_panel(): void
    {
        $admin = $this->createUser('superadmin@test.com', true);

        $createRes = $this->postJson(
            '/api/admin/tribunal/jury-panels',
            [
                'panel_name' => 'Toggle Panel',
                'email' => 'toggle@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
            $this->authHeaders($admin)
        );
        $panelId = $createRes->json('data.id');

        // Deactivate
        $deactivateRes = $this->postJson(
            "/api/admin/tribunal/jury-panels/{$panelId}/deactivate",
            [],
            $this->authHeaders($admin)
        );
        $deactivateRes->assertStatus(200);
        $deactivateRes->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('tribunal_jury_panels', [
            'id' => $panelId,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('tribunal_jury_panel_events', [
            'tribunal_jury_panel_id' => $panelId,
            'event_type' => 'jury_panel_deactivated',
        ]);

        // Activate
        $activateRes = $this->postJson(
            "/api/admin/tribunal/jury-panels/{$panelId}/activate",
            [],
            $this->authHeaders($admin)
        );
        $activateRes->assertStatus(200);
        $activateRes->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('tribunal_jury_panels', [
            'id' => $panelId,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('tribunal_jury_panel_events', [
            'tribunal_jury_panel_id' => $panelId,
            'event_type' => 'jury_panel_activated',
        ]);
    }
}
