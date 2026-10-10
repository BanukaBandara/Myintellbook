<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function case_a_invalid_google_token_returns_sanitized_response_without_exception_leakage(): void
    {
        $response = $this->postJson('/api/auth/google', [
            'token' => 'invalid-malformed-token-xyz',
        ]);

        // Expect either 401 or 500 depending on Google Client validation failure
        $this->assertContains($response->status(), [401, 500]);

        $content = $response->getContent();

        // Verify zero leakage of PHP Exception internal structures
        $this->assertStringNotContainsString('"xdebug_message"', $content);
        $this->assertStringNotContainsString('Stack trace:', $content);
        $this->assertStringNotContainsString('vendor/', $content);
        $this->assertStringNotContainsString('GoogleController.php', $content);
        $this->assertStringNotContainsString('Google\\\\Client', $content);
    }

    #[Test]
    public function case_a2_google_oauth_exception_handling_is_sanitized_and_safe(): void
    {
        // Mock Google\Client to throw an exception with simulated internal details
        $mockClient = \Mockery::mock(\Google\Client::class);
        $mockClient->shouldReceive('setClientId')->andReturnNull();
        $mockClient->shouldReceive('verifyIdToken')
            ->andThrow(new \RuntimeException('Sensitive DB connection timeout at /var/www/secret.php:123 with token=secret_oauth_token_val'));
        $this->app->instance(\Google\Client::class, $mockClient);

        // Spy on Log facade
        \Illuminate\Support\Facades\Log::shouldReceive('error')
            ->once()
            ->with(
                'Google authentication failed.',
                \Mockery::on(function ($context) {
                    // Confirm safe logging: exception_class logged, NO sensitive message, NO tokens, NO payload
                    return isset($context['exception_class'])
                        && $context['exception_class'] === \RuntimeException::class
                        && !isset($context['message'])
                        && !isset($context['token']);
                })
            );

        $response = $this->postJson('/api/auth/google', [
            'token' => 'some-dummy-token',
        ]);

        $response->assertStatus(500);
        $response->assertExactJson([
            'code' => 500,
            'status' => false,
            'message' => 'Google authentication failed.',
        ]);

        $content = $response->getContent();
        $this->assertStringNotContainsString('Sensitive DB connection', $content);
        $this->assertStringNotContainsString('secret_oauth_token_val', $content);
        $this->assertStringNotContainsString('/var/www/secret.php', $content);
        $this->assertStringNotContainsString('RuntimeException', $content);
        $this->assertStringNotContainsString('stack', strtolower($content));
    }

    #[Test]
    public function case_b_google_oauth_rejects_super_admin_account_with_403_and_no_token(): void
    {
        $admin = User::create([
            'email' => 'admin_oauth_test@example.com',
            'name' => 'Super Admin',
            'password' => Hash::make('AdminPass123!'),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        // Mock Google client id token payload
        $fakeGooglePayload = [
            'sub' => 'google-admin-12345',
            'email' => 'admin_oauth_test@example.com',
            'email_verified' => true,
            'name' => 'Super Admin',
        ];

        // Mock Google\Client instance
        $mockClient = \Mockery::mock(\Google\Client::class);
        $mockClient->shouldReceive('setClientId')->andReturnNull();
        $mockClient->shouldReceive('verifyIdToken')
            ->with('valid-admin-google-token')
            ->andReturn($fakeGooglePayload);
        $this->app->instance(\Google\Client::class, $mockClient);

        $response = $this->postJson('/api/auth/google', [
            'token' => 'valid-admin-google-token',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'code' => 403,
            'status' => false,
            'requires_admin_portal' => true,
        ]);

        // Ensure no normal ApiToken was issued for this admin account
        $this->assertEquals(0, ApiToken::where('user_id', $admin->id)->count());
    }

    #[Test]
    public function case_c_excessive_login_attempts_trigger_rate_limiting_429(): void
    {
        $user = User::create([
            'email' => 'ratelimit_user@example.com',
            'password' => Hash::make('CorrectPassword123!'),
            'is_admin' => false,
        ]);

        // 5 wrong passwords are rejected normally
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'ratelimit_user@example.com',
                'password' => 'WrongPassword!',
            ])->assertStatus(401);
        }

        // Then the account is locked: even the correct password is refused with 429
        $rateLimitedResponse = $this->postJson('/api/login', [
            'email' => 'ratelimit_user@example.com',
            'password' => 'CorrectPassword123!',
        ]);

        $rateLimitedResponse->assertStatus(429);
        $this->assertStringContainsString('Too many failed sign-in attempts', $rateLimitedResponse->json('message'));
    }

    #[Test]
    public function case_c2_excessive_registration_attempts_trigger_rate_limiting_429(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/register', [
                'email' => "user_{$i}@example.com",
                'password' => 'Short', // invalid to fail fast
                'password_confirmation' => 'Short',
            ]);
            $this->assertEquals(422, $response->status());
        }

        // 11th request must receive HTTP 429
        $rateLimitedResponse = $this->postJson('/api/register', [
            'email' => 'user_overflow@example.com',
            'password' => 'Short',
            'password_confirmation' => 'Short',
        ]);

        $rateLimitedResponse->assertStatus(429);
    }

    #[Test]
    public function case_c3_excessive_password_reset_requests_trigger_rate_limiting_429(): void
    {
        // 5 requests per email per hour; unknown emails get the same 200 as real ones
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/password/reset', [
                'email' => 'nonexistent@example.com',
            ])->assertStatus(200);
        }

        // 6th request must receive HTTP 429
        $rateLimitedResponse = $this->postJson('/api/password/reset', [
            'email' => 'nonexistent@example.com',
        ]);

        $rateLimitedResponse->assertStatus(429);
    }

    #[Test]
    public function case_d_cors_rejects_untrusted_origin_and_allows_legitimate_local_origin(): void
    {
        // 1. Untrusted origin request
        $untrustedResponse = $this->withHeaders([
            'Origin' => 'https://malicious-attacker.com',
        ])->getJson('/api/categories');

        // Access-Control-Allow-Origin must NOT reflect the malicious origin
        $this->assertNotEquals(
            'https://malicious-attacker.com',
            $untrustedResponse->headers->get('Access-Control-Allow-Origin')
        );

        // 2. Legitimate local Vite development origin request
        $trustedResponse = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
        ])->getJson('/api/categories');

        $this->assertEquals(
            'http://localhost:5173',
            $trustedResponse->headers->get('Access-Control-Allow-Origin')
        );
    }
}
