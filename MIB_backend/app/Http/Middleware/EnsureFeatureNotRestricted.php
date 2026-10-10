<?php

namespace App\Http\Middleware;

use App\Services\InternalTribunal\AccountFeatureRestrictionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureNotRestricted
{
    public function __construct(
        protected AccountFeatureRestrictionService $restrictionService
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user() ?? \Illuminate\Support\Facades\Auth::user();

        if ($user && !$user->isAdmin() && !$user->isJuryPanelAccount()) {
            $restriction = $this->restrictionService->getActiveRestriction($user, $feature);

            if ($restriction) {
                return response()->json(
                    $this->restrictionService->formatRestrictionPayload($feature, $restriction),
                    403
                );
            }
        }

        return $next($request);
    }
}
