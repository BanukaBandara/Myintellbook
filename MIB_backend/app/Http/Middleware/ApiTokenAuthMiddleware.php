<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiToken;
use Illuminate\Support\Facades\Auth;

class ApiTokenAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');
 

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized1'], 401);
        }

        $token = substr($authHeader, 7); // remove "Bearer "

        $hashedToken = hash('sha256', $token);

        $apiToken = ApiToken::where('token', $hashedToken)
            ->where('expires_at', '>', now())
            ->first();

        if (!$apiToken) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Set the authenticated user
        Auth::setUser($apiToken->user);

        // Account suspension check (defense-in-depth)
        $user = $apiToken->user;
        if ($user && !$user->isAdmin() && !$user->isJuryPanelAccount()) {
            $suspensionService = app(\App\Services\InternalTribunal\AccountSuspensionService::class);
            $activeSuspension = $suspensionService->getActiveSuspension($user);
            if ($activeSuspension) {
                return response()->json($suspensionService->formatSuspensionPayload($activeSuspension), 403);
            }
        }

        return $next($request);
    }
}
