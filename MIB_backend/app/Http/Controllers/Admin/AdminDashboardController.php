<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\ProfessionalVerification;
use App\Models\TribunalCase;
use App\Models\TribunalJuryPanel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Aggregated statistics for the Super Admin dashboard.
     * Excludes confidential case deliberations or private user communications.
     */
    public function stats(Request $request): JsonResponse
    {
        $totalUsers = User::count();

        $pendingVerifications = ProfessionalVerification::where('status', ProfessionalVerificationStatus::Pending->value)->count();
        $verifiedLawyers = ProfessionalVerification::where('status', ProfessionalVerificationStatus::Verified->value)
            ->where('profession_type', ProfessionalType::AttorneyAtLaw->value)
            ->count();

        $activeJuryPanels = TribunalJuryPanel::where('is_active', true)->count();
        $inactiveJuryPanels = TribunalJuryPanel::where('is_active', false)->count();

        $totalCases = TribunalCase::count();
        $casesWaitingJury = TribunalCase::where('status', 'draft')
            ->orWhereNull('assigned_jury_panel_id')
            ->count();
        $casesInHearing = TribunalCase::where('status', 'hearing')->count();
        $casesInDeliberation = TribunalCase::where('status', 'deliberation')->count();
        $casesCompleted = TribunalCase::whereIn('status', ['decided', 'closed', 'dismissed'])->count();

        return response()->json([
            'status' => true,
            'data' => [
                'users' => [
                    'total' => $totalUsers,
                ],
                'verifications' => [
                    'pending' => $pendingVerifications,
                    'verified_lawyers' => $verifiedLawyers,
                ],
                'jury_panels' => [
                    'active' => $activeJuryPanels,
                    'inactive' => $inactiveJuryPanels,
                    'total' => $activeJuryPanels + $inactiveJuryPanels,
                ],
                'tribunal_cases' => [
                    'total' => $totalCases,
                    'waiting_jury' => $casesWaitingJury,
                    'in_hearing' => $casesInHearing,
                    'in_deliberation' => $casesInDeliberation,
                    'completed' => $casesCompleted,
                ],
            ],
        ]);
    }
}
