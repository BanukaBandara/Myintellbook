<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Handle an incoming request.
     *
     * Ensure the authenticated user has is_admin = 1.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?: auth()->user();

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden. Administrator access required.',
            ], 403);
        }

        return $next($request);
    }
}
