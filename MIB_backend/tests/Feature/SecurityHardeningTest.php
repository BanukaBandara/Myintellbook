<?php

namespace Tests\Feature;

use App\Mail\passwordReset;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\PasswordResetToken;
use App\Models\Profile;
use App\Models\User;
use App\Models\profession;
use App\Rules\Base64Image;
use App\Support\SafeError;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 2 security hardening: headers, safe errors, auth hardening, verification gate,
 * validation and server-side privacy.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(string $email, bool $verified = true, bool $isAdmin = false): array
    {
        $user = User::create([
            'email' => $email,
            'password' => Hash::make('Secret123!'),
            'is_admin' => $isAdmin,
        ]);
        if (!$verified) {
            $user->forceFill(['email_verified_at' => null])->save();
        }

        $raw = Str::random(40);
        ApiToken::create(['user_id' => $user->id, 'token' => hash('sha256', $raw), 'expires_at' => now()->addDay()]);

        return [$user, ['Authorization' => "Bearer {$raw}", 'Accept' => 'application/json']];
    }

    // ---- Security headers & safe errors ----------------------------------------------------

    #[Test]
    public function api_responses_carry_security_headers(): void
    {
        $response = $this->getJson('/api/tribunal/reports/verify/does-not-exist');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertFalse($response->headers->has('X-Powered-By'));
    }

    #[Test]
    public function unhandled_exceptions_return_a_generic_message_with_a_reference(): void
    {
        config(['app.debug' => false]);
        Route::middleware('api')->get('/api/__test/boom', function () {
            throw new \LogicException('SQLSTATE[42S02]: Base table users_secret not found');
        });

        $response = $this->getJson('/api/__test/boom');

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Something went wrong. Please try again.');
        $this->assertNotEmpty($response->json('reference'));
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    #[Test]
    public function missing_models_do_not_reveal_class_names(): void
    {
        config(['app.debug' => false]);
        Route::middleware('api')->get('/api/__test/missing', fn () => User::findOrFail(987654));

        $response = $this->getJson('/api/__test/missing');

        $response->assertStatus(404)->assertJsonPath('message', 'The requested resource was not found.');
        $this->assertStringNotContainsString('App\\Models', $response->getContent());
    }

    #[Test]
    public function safe_error_only_passes_through_application_messages(): void
    {
        $this->assertSame('Profile not found.', SafeError::message(new \RuntimeException('Profile not found.')));
        $this->assertSame(SafeError::GENERIC, SafeError::message(new QueryException('mysql', 'select * from secret', [], new \Exception('x'))));
        $this->assertSame(SafeError::GENERIC, SafeError::message(new \ErrorException('Attempt to read property "id" on null')));
        $this->assertSame(SafeError::GENERIC, SafeError::message(new \TypeError('Argument #1 must be of type int')));
    }

    // ---- Password reset ---------------------------------------------------------------------

    #[Test]
    public function password_reset_request_does_not_reveal_whether_an_email_exists(): void
    {
        Mail::fake();
        Bus::fake();
        User::create(['email' => 'known@example.com', 'password' => Hash::make('Secret123!')]);

        $known = $this->postJson('/api/password/reset', ['email' => 'known@example.com']);
        $unknown = $this->postJson('/api/password/reset', ['email' => 'unknown@example.com']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json('message'), $unknown->json('message'));
    }

    #[Test]
    public function password_reset_token_is_stored_hashed_and_resets_revoke_sessions(): void
    {
        Mail::fake();
        Bus::fake();
        [$user] = $this->userWithToken('reset@example.com');

        $this->postJson('/api/password/reset', ['email' => 'reset@example.com'])->assertOk();

        $sentUrl = null;
        Mail::assertSent(passwordReset::class, function (passwordReset $mail) use (&$sentUrl) {
            $sentUrl = $mail->verificationUrl;
            return true;
        });
        $rawToken = substr($sentUrl, strrpos($sentUrl, ':') + 1);

        $stored = PasswordResetToken::where('email', 'reset@example.com')->value('token');
        $this->assertNotSame($rawToken, $stored, 'The raw reset token must not be stored');
        $this->assertSame(hash('sha256', $rawToken), $stored);

        $this->postJson("/api/password/reset/{$rawToken}", [
            'password' => 'NewSecret456!',
            'password_confirmation' => 'NewSecret456!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewSecret456!', $user->fresh()->password));
        $this->assertSame(0, ApiToken::where('user_id', $user->id)->count(), 'Existing sessions must be revoked');
        $this->assertNull(PasswordResetToken::where('email', 'reset@example.com')->first());
    }

    #[Test]
    public function expired_password_reset_tokens_are_rejected_even_without_the_queue(): void
    {
        $user = User::create(['email' => 'old@example.com', 'password' => Hash::make('Secret123!')]);
        PasswordResetToken::create([
            'email' => 'old@example.com',
            'token' => hash('sha256', 'stale-token'),
            'created_at' => now()->subMinutes(61),
        ]);

        $this->postJson('/api/password/reset/stale-token', [
            'password' => 'NewSecret456!',
            'password_confirmation' => 'NewSecret456!',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('Secret123!', $user->fresh()->password));
    }

    // ---- Admin tokens -------------------------------------------------------------------------

    #[Test]
    public function admin_tokens_expire_after_the_short_admin_lifetime(): void
    {
        User::create(['email' => 'boss@example.com', 'password' => Hash::make('AdminPass123!'), 'is_admin' => true]);

        $response = $this->postJson('/api/admin/login', ['email' => 'boss@example.com', 'password' => 'AdminPass123!']);

        $response->assertOk()->assertJsonPath('expires_in_minutes', 480);
        $expiresAt = ApiToken::where('token', hash('sha256', $response->json('token')))->value('expires_at');
        $minutes = now()->diffInMinutes($expiresAt);
        $this->assertTrue($minutes > 470 && $minutes <= 480, "Admin token lifetime was {$minutes} minutes");
    }

    // ---- Email verification gate -------------------------------------------------------------

    #[Test]
    public function unverified_accounts_cannot_use_sensitive_actions_and_can_request_a_new_link(): void
    {
        Mail::fake();
        Bus::fake();
        [$user, $headers] = $this->userWithToken('unverified@example.com', verified: false);

        $this->withHeaders($headers)
            ->postJson('/api/internal-reports', [])
            ->assertStatus(403)
            ->assertJsonPath('requires_email_verification', true);

        $this->withHeaders($headers)
            ->postJson('/api/email/verification-notification')
            ->assertOk();

        $this->assertNotNull($user->fresh()->email_verification_token);
    }

    #[Test]
    public function google_sign_in_with_an_unverified_email_is_refused(): void
    {
        $existing = User::create(['email' => 'victim@example.com', 'password' => Hash::make('Secret123!')]);

        $mockClient = \Mockery::mock(\Google\Client::class);
        $mockClient->shouldReceive('setClientId')->andReturnNull();
        $mockClient->shouldReceive('verifyIdToken')->andReturn([
            'sub' => 'google-attacker-1',
            'email' => 'victim@example.com',
            'email_verified' => false,
        ]);
        $this->app->instance(\Google\Client::class, $mockClient);

        $response = $this->postJson('/api/auth/google', ['token' => 'attacker-token']);

        $response->assertStatus(401);
        $this->assertNull($response->json('token'));
        $this->assertNull($existing->fresh()->google_id, 'The existing account must not be linked');
    }

    // ---- Validation ---------------------------------------------------------------------------

    #[Test]
    public function settings_only_accept_known_visibility_keys_and_values(): void
    {
        [$user, $headers] = $this->userWithToken('settings@example.com');

        $this->withHeaders($headers)->postJson('/api/set_settings', ['is_admin' => 'Public'])->assertStatus(422);
        $this->withHeaders($headers)->postJson('/api/set_settings', ['gender' => 'Everyone'])->assertStatus(422);
        $this->withHeaders($headers)->postJson('/api/set_settings', ['gender' => 'Only Me'])->assertOk();

        $this->assertSame('Only Me', $user->fresh()->getSetting('gender'));
    }

    #[Test]
    public function complaints_cannot_appoint_yourself_as_juror(): void
    {
        [$user, $headers] = $this->userWithToken('complainer@example.com');
        $defendant = User::create(['email' => 'defendant@example.com', 'password' => Hash::make('Secret123!')]);

        $this->withHeaders($headers)->postJson('/api/submit-complains', [
            'defendent' => $defendant->id,
            'category' => 'Misconduct',
            'from' => 1,
            'jury' => $user->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['jury']);
    }

    #[Test]
    public function search_terms_are_length_limited(): void
    {
        [, $headers] = $this->userWithToken('searcher@example.com');

        $this->withHeaders($headers)
            ->getJson('/api/tribunal/respondents/search?q='.str_repeat('a', 101))
            ->assertStatus(422);
    }

    #[Test]
    public function profile_images_must_be_real_png_jpeg_or_webp(): void
    {
        $png = 'data:image/png;base64,'.base64_encode(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));
        $svg = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $fakePng = 'data:image/png;base64,'.base64_encode('<?php echo "not an image"; ?>');
        $oversized = 'data:image/png;base64,'.base64_encode(str_repeat("\x89PNG", 600000));

        $passes = fn (string $value) => Validator::make(['image' => $value], ['image' => [new Base64Image()]])->passes();

        $this->assertTrue($passes($png));
        $this->assertFalse($passes($svg), 'SVG must be rejected');
        $this->assertFalse($passes($fakePng), 'Content must match the declared type');
        $this->assertFalse($passes($oversized), 'Images over 2 MB must be rejected');
    }

    // ---- Privacy ------------------------------------------------------------------------------

    #[Test]
    public function only_me_fields_are_removed_for_other_viewers_but_kept_for_the_owner(): void
    {
        [$owner, $ownerHeaders] = $this->userWithToken('owner@example.com');
        [, $viewerHeaders] = $this->userWithToken('viewer@example.com');

        $category = Category::create(['name' => 'Privacy test']);
        $profession = profession::create(['category_id' => $category->id, 'name' => 'Privacy test']);
        Profile::create([
            'user_id' => $owner->id,
            'first_name' => 'Olivia',
            'last_name' => 'Owner',
            'gender' => 2,
            'profession_id' => $profession->id,
            'birth_date' => '1990-01-01',
        ]);
        $owner->workExperiances()->create([
            'title' => 'Engineer',
            'company' => 'Secret Employer Ltd',
            'currently_working' => true,
            'location' => 'Colombo',
            'selectEmpType' => 1,
            'locationType' => 1,
            'starting_date' => '2020-01-01',
            'positionType' => 'executive',
        ]);
        $owner->setSetting('gender', 'Only Me');
        $owner->setSetting('organization', 'Only Me');

        $this->withHeaders($viewerHeaders)->getJson("/api/users/{$owner->id}")
            ->assertOk()
            ->assertJsonPath('user.gender', null)
            ->assertJsonPath('user.experiance.0.company', '')
            ->assertJsonPath('user.profession.company', '')
            ->assertJsonPath('user.experiance.0.title', 'Engineer')
            ->assertJsonPath('user.first_name', 'Olivia');

        $this->withHeaders($ownerHeaders)->getJson("/api/users/{$owner->id}")
            ->assertOk()
            ->assertJsonPath('user.gender', 2)
            ->assertJsonPath('user.experiance.0.company', 'Secret Employer Ltd');
    }
}
