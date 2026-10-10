<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks sensitive actions (filing cases, submitting evidence, reports, verifications,
 * testaments) until the account's email address has been confirmed. The frontend shows a
 * prompt with a "resend verification email" action when it sees requires_email_verification.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?: auth()->user();

        if ($user && $user->email_verified_at === null) {
            return response()->json([
                'code' => 403,
                'status' => false,
                'requires_email_verification' => true,
                'message' => 'Please verify your email address to use this feature. Check your inbox, or request a new verification email.',
            ], 403);
        }

        return $next($request);
    }
}
