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
        try {
            $client = app()->make(GoogleClient::class);
            $client->setClientId(config('services.google.client_id'));
            $payload = $client->verifyIdToken($request->token);

            if ($payload) {
                $googleId = $payload['sub']; // Google unique ID
                $email    = $payload['email'];
                $name     = $payload['name'];

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name'      => $name,
                        'google_id' => $googleId,
                        'password'  => bcrypt(str()->random(16)),
                    ]
                );

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
