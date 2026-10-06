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
use App\Models\User;
use App\Models\profession;
use App\Services\Tribunal\TribunalJuryPanelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TribunalJuryPortalTest extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        $cat = Category::create(['name' => 'Legal Portal Category']);
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
            'years_of_experience' => 7,
            'submitted_at' => now()->subDays(5),
            'reviewed_at' => now()->subDays(1),
            'expires_at' => now()->addYears(2),
        ]);

        return $lawyer;
    }

    #[Test]
    public function active_jury_panel_account_can_access_get_jury_me(): void
    {
        [$panel, $panelUser] = $this->createJuryPanel('Jury Panel 01', 'jp01@myintellbook.test', 'active');

        $response = $this->getJson('/api/jury/me', $this->authHeaders($panelUser));

        $response->assertStatus(200);
        $response->assertJsonPath('is_jury_panel', true);
        $response->assertJsonPath('panel.panel_code', $panel->panel_code);
        $response->assertJsonPath('panel.panel_name', 'Jury Panel 01');
        $response->assertJsonPath('panel.status', 'active');

        // Verify secrets are NOT exposed
        $response->assertJsonMissing(['password']);
        $response->assertJsonMissing(['password_hash']);
        $response->assertJsonMissing(['remember_token']);
    }

    #[Test]
    public function normal_user_receives_403_from_jury_me(): void
    {
        $normalUser = $this->createUser('normal@test.com', false);

        $response = $this->getJson('/api/jury/me', $this->authHeaders($normalUser));

        $response->assertStatus(403);
    }

    #[Test]
    public function verified_lawyer_receives_403_from_jury_me(): void
    {
        $lawyer = $this->createVerifiedLawyer('lawyer@test.com');

        $response = $this->getJson('/api/jury/me', $this->authHeaders($lawyer));

        $response->assertStatus(403);
    }

    #[Test]
    public function super_admin_receives_403_from_jury_me_unless_actual_panel_account(): void
    {
        $admin = $this->createUser('superadmin@test.com', true);

        $response = $this->getJson('/api/jury/me', $this->authHeaders($admin));

        $response->assertStatus(403);
    }

    #[Test]
    public function active_jury_panel_can_access_get_jury_cases(): void
    {
        [$panel, $panelUser] = $this->createJuryPanel('Jury Panel 02', 'jp02@myintellbook.test', 'active');

        $response = $this->getJson('/api/jury/cases', $this->authHeaders($panelUser));

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data', []);
    }

    #[Test]
    public function normal_user_cannot_access_jury_cases_endpoint(): void
    {
        $normalUser = $this->createUser('normal2@test.com', false);

        $response = $this->getJson('/api/jury/cases', $this->authHeaders($normalUser));

        $response->assertStatus(403);
    }

    #[Test]
    public function inactive_jury_panel_is_blocked_from_jury_portal(): void
    {
        [$panel, $panelUser] = $this->createJuryPanel('Inactive Panel', 'inactive@myintellbook.test', 'inactive');

        $responseMe = $this->getJson('/api/jury/me', $this->authHeaders($panelUser));
        $responseMe->assertStatus(403);
        $responseMe->assertJsonPath('panel_status', 'inactive');

        $responseCases = $this->getJson('/api/jury/cases', $this->authHeaders($panelUser));
        $responseCases->assertStatus(403);
        $responseCases->assertJsonPath('panel_status', 'inactive');
    }

    #[Test]
    public function suspended_jury_panel_is_blocked_from_jury_portal(): void
    {
        [$panel, $panelUser] = $this->createJuryPanel('Suspended Panel', 'suspended@myintellbook.test', 'suspended');

        $responseMe = $this->getJson('/api/jury/me', $this->authHeaders($panelUser));
        $responseMe->assertStatus(403);
        $responseMe->assertJsonPath('panel_status', 'suspended');

        $responseCases = $this->getJson('/api/jury/cases', $this->authHeaders($panelUser));
        $responseCases->assertStatus(403);
        $responseCases->assertJsonPath('panel_status', 'suspended');
    }

    #[Test]
    public function jury_panel_remains_not_representative_eligible(): void
    {
        [$panel, $panelUser] = $this->createJuryPanel('Rep Guard Panel', 'repguard@myintellbook.test', 'active');

        $this->assertFalse($panelUser->canActAsLegalRepresentative());
        $this->assertFalse($panelUser->isAdmin());
    }

    #[Test]
    public function existing_super_admin_jury_panel_management_still_works(): void
    {
        $admin = $this->createUser('admin_mgmt@test.com', true);

        // List
        $responseList = $this->getJson('/api/admin/tribunal/jury-panels', $this->authHeaders($admin));
        $responseList->assertStatus(200);

        // Create
        $responseCreate = $this->postJson(
            '/api/admin/tribunal/jury-panels',
            [
                'panel_name' => 'Mgmt Panel Test',
                'email' => 'mgmt_test@myintellbook.test',
                'password' => 'Pass123456!',
                'password_confirmation' => 'Pass123456!',
            ],
            $this->authHeaders($admin)
        );
        $responseCreate->assertStatus(201);
    }
}
