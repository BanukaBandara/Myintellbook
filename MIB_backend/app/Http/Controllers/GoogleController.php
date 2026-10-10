<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\User;
use Google\Client as GoogleClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GoogleController extends Controller
{
    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        try {
            $client = app()->make(GoogleClient::class);
            $client->setClientId(config('services.google.client_id'));
            $payload = $client->verifyIdToken($request->token);

            if ($payload) {
                $googleId = (string) ($payload['sub'] ?? '');
                $email    = strtolower((string) ($payload['email'] ?? ''));

                // Only trust the email if Google has verified that this Google account owns it;
                // otherwise anyone could sign in to an existing account by claiming its address.
                $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($googleId === '' || $email === '' || !$emailVerified) {
                    return response()->json([
                        'code' => 401,
                        'status' => false,
                        'message' => 'Your Google account email is not verified. Please verify it with Google, or sign in with email and password.',
                    ], 401);
                }

                // Prefer the stable Google ID; fall back to email to link existing accounts.
                $user = User::where('google_id', $googleId)->first()
                    ?? User::where('email', $email)->first();

                if ($user && $user->google_id && $user->google_id !== $googleId) {
                    // This email is already linked to a different Google account.
                    return response()->json([
                        'code' => 401,
                        'status' => false,
                        'message' => 'This account is linked to a different Google account.',
                    ], 401);
                }

                if (!$user) {
                    $user = User::create([
                        'email'     => $email,
                        'google_id' => $googleId,
                        'password'  => bcrypt(str()->random(40)),
                    ]);
                } elseif (!$user->google_id) {
                    $user->google_id = $googleId;
                }

                // Google has verified the address, which is what our own email verification proves.
                if ($user->email_verified_at === null) {
                    $user->email_verified_at = now();
                }
                if ($user->isDirty()) {
                    $user->save();
                }

                // SEC-MED-02: Super Admin isolation check
                if ($user->isAdmin()) {
                    return response()->json([
                        'code' => 403,
                        'status' => false,
                        'requires_admin_portal' => true,
                        'message' => 'This administrator account must use the admin login portal.',
                    ], 403);
                }

                // Account suspension check (defense-in-depth)
                $suspensionService = app(\App\Services\InternalTribunal\AccountSuspensionService::class);
                $activeSuspension = $suspensionService->getActiveSuspension($user);
                if ($activeSuspension) {
                    return response()->json($suspensionService->formatSuspensionPayload($activeSuspension), 403);
                }

                Auth::login($user);

                $apiToken = new ApiToken();
                $token = $apiToken->tokenGenerate($user);
                $user->load('profile');

                return response()->json([
                    'code' => 200,
                    'token' => $token,
                    'user'  => [
                        ...$user->toArray(),
                        'is_profile_completed' => $user->isProfileCompleted(),
                    ],
                ]);
            }

            return response()->json([
                'code' => 401,
                'status' => false,
                'message' => 'Invalid Google token',
                'error' => 'Invalid Google token',
            ], 401);

        } catch (\Throwable $e) {
            // SEC-HIGH-01: Safe server-side logging without leaking tokens, messages, or sensitive payload
            Log::error('Google authentication failed.', [
                'exception_class' => get_class($e),
            ]);

            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Google authentication failed.',
            ], 500);
        }
    }
}
