<?php

namespace Tests\Feature;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalJuryPanelStatus;
use App\Models\ApiToken;
use App\Models\ProfessionalVerification;
use App\Models\TribunalCase;
use App\Models\TribunalJuryPanel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tokenFor(User $user): string
    {
        $apiToken = new ApiToken();
        return $apiToken->tokenGenerate($user);
    }

    protected function createNormalUser(string $email = 'normal@example.com', string $password = 'Password123!'): User
    {
        return User::create([
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);
    }

    protected function createAdminUser(string $email = 'admin@example.com', string $password = 'AdminPass123!'): User
    {
        return User::create([
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);
    }

    protected function createJuryPanelUser(string $email = 'jury@example.com', string $password = 'JuryPass123!'): User
    {
        $admin = $this->createAdminUser('creator_' . Str::random(5) . '@example.com');

        $user = User::create([
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);

        TribunalJuryPanel::create([
            'panel_code' => 'PANEL-TST-' . Str::random(4),
            'panel_name' => 'Test Panel',
            'login_user_id' => $user->id,
            'status' => TribunalJuryPanelStatus::Active->value,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        return $user;
    }

    #[Test]
    public function normal_user_can_still_login_through_normal_login_endpoint(): void
    {
        $user = $this->createNormalUser('normal@example.com', 'Secret123!');

        $response = $this->postJson('/api/login', [
            'email' => 'normal@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'code' => 200,
            'status' => true,
            'user' => [
                'id' => $user->id,
                'email' => 'normal@example.com',
                'is_admin' => false,
                'is_jury_panel' => false,
            ],
        ]);
        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function jury_panel_can_still_login_through_normal_login_endpoint(): void
    {
        $jury = $this->createJuryPanelUser('juror@example.com', 'JuryPass123!');

        $response = $this->postJson('/api/login', [
            'email' => 'juror@example.com',
            'password' => 'JuryPass123!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'code' => 200,
            'status' => true,
            'user' => [
                'id' => $jury->id,
                'is_admin' => false,
                'is_jury_panel' => true,
            ],
        ]);
        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function super_admin_is_blocked_from_normal_login_and_receives_no_token(): void
    {
        $admin = $this->createAdminUser('admin@example.com', 'AdminPass123!');

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'AdminPass123!',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'code' => 403,
            'status' => false,
            'requires_admin_portal' => true,
        ]);
        $this->assertNull($response->json('token'));
        $this->assertDatabaseMissing('api_tokens', ['user_id' => $admin->id]);
    }

    #[Test]
    public function super_admin_can_authenticate_via_admin_login(): void
    {
        $admin = $this->createAdminUser('admin@example.com', 'AdminPass123!');

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'AdminPass123!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'code' => 200,
            'status' => true,
            'user' => [
                'id' => $admin->id,
                'email' => 'admin@example.com',
                'is_admin' => true,
            ],
        ]);
        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function normal_user_cannot_authenticate_via_admin_login(): void
    {
        $this->createNormalUser('normal@example.com', 'Secret123!');

        $response = $this->postJson('/api/admin/login', [
            'email' => 'normal@example.com',
            'password' => 'Secret123!',
        ]);

        // Same answer as a wrong password, so the admin portal can't confirm user passwords.
        $response->assertStatus(401);
        $response->assertJson([
            'code' => 401,
            'status' => false,
            'message' => 'Invalid admin credentials.',
        ]);
        $this->assertNull($response->json('token'));
    }

    #[Test]
    public function jury_panel_cannot_authenticate_via_admin_login(): void
    {
        $this->createJuryPanelUser('jury@example.com', 'JuryPass123!');

        $response = $this->postJson('/api/admin/login', [
            'email' => 'jury@example.com',
            'password' => 'JuryPass123!',
        ]);

        // Same answer as a wrong password, so the admin portal can't confirm user passwords.
        $response->assertStatus(401);
        $response->assertJson([
            'code' => 401,
            'status' => false,
            'message' => 'Invalid admin credentials.',
        ]);
        $this->assertNull($response->json('token'));
    }

    #[Test]
    public function invalid_admin_password_is_rejected(): void
    {
        $this->createAdminUser('admin@example.com', 'AdminPass123!');

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'code' => 401,
            'status' => false,
        ]);
    }

    #[Test]
    public function admin_me_endpoint_returns_admin_details(): void
    {
        $admin = $this->createAdminUser('admin@example.com');
        $token = $this->tokenFor($admin);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/me');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data' => [
                'id' => $admin->id,
                'email' => 'admin@example.com',
                'is_admin' => true,
            ],
        ]);
    }

    #[Test]
    public function admin_me_endpoint_rejects_normal_user(): void
    {
        $user = $this->createNormalUser('normal@example.com');
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/me');

        $response->assertStatus(403);
    }

    #[Test]
    public function admin_logout_revokes_token(): void
    {
        $admin = $this->createAdminUser('admin@example.com');
        $token = $this->tokenFor($admin);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/logout');

        $response->assertStatus(200);

        // Next request using revoked token should fail 401
        $subsequent = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/me');

        $subsequent->assertStatus(401);
    }

    #[Test]
    public function normal_user_cannot_access_admin_jury_panel_management(): void
    {
        $user = $this->createNormalUser('normal@example.com');
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/tribunal/jury-panels');

        $response->assertStatus(403);
    }

    #[Test]
    public function jury_panel_cannot_access_admin_apis(): void
    {
        $jury = $this->createJuryPanelUser('jury@example.com');
        $token = $this->tokenFor($jury);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/tribunal/jury-panels');

        $response->assertStatus(403);
    }

    #[Test]
    public function super_admin_can_access_admin_jury_panels(): void
    {
        $admin = $this->createAdminUser('admin@example.com');
        $token = $this->tokenFor($admin);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/tribunal/jury-panels');

        $response->assertStatus(200);
    }

    #[Test]
    public function super_admin_can_access_dashboard_stats(): void
    {
        $admin = $this->createAdminUser('admin@example.com');
        $token = $this->tokenFor($admin);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/dashboard/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'users' => ['total'],
                'verifications' => ['pending', 'verified_lawyers'],
                'jury_panels' => ['active', 'inactive', 'total'],
                'tribunal_cases' => ['total', 'waiting_jury', 'in_hearing', 'in_deliberation', 'completed'],
            ],
        ]);
    }
}
