<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJuryPanel
{
    /**
     * Handle an incoming request.
     * Ensure the authenticated user is an active Jury Panel account.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?: auth()->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $panel = $user->juryPanel;

        if (!$panel) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden. Jury Panel access required.',
            ], 403);
        }

        if (!$panel->isActive()) {
            $statusText = $panel->status instanceof \BackedEnum ? $panel->status->value : (string) $panel->status;
            return response()->json([
                'status' => false,
                'message' => "Your Jury Panel account is currently {$statusText}.",
                'panel_status' => $statusText,
            ], 403);
        }

        if (app(\App\Services\InternalTribunal\AccountJuryPanelDisciplineService::class)->hasActiveDeactivation($panel)) {
            return response()->json([
                'status' => false,
                'message' => 'Your Jury Panel account is currently deactivated.',
                'panel_status' => 'deactivated',
            ], 403);
        }

        $request->attributes->set('jury_panel', $panel);

        return $next($request);
    }
}
