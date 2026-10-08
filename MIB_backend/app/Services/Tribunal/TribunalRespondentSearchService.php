<?php

namespace App\Services\Tribunal;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TribunalRespondentSearchService
{
    /**
     * Minimum query character count required to search.
     */
    public const MIN_QUERY_LENGTH = 3;

    /**
     * Maximum allowed results to return per search request.
     */
    public const MAX_SEARCH_LIMIT = 20;

    /**
     * Default results count.
     */
    public const DEFAULT_SEARCH_LIMIT = 10;

    /**
     * Search eligible platform users for naming as case respondent.
     *
     * Excludes:
     * - The currently authenticated user (self)
     * - Dedicated Jury Panel login accounts
     * - Super Admin / platform management accounts
     */
    public function search(string $query, int $currentUserId, int $limit = self::DEFAULT_SEARCH_LIMIT): Collection
    {
        $clean = trim($query);

        // Require minimum character length to prevent user enumeration
        if (mb_strlen($clean) < self::MIN_QUERY_LENGTH) {
            return new Collection();
        }

        $cappedLimit = min(max($limit, 1), self::MAX_SEARCH_LIMIT);

        return User::query()
            ->with([
                'profile',
                'latestProfessionalVerification',
            ])
            // 1. Exclude authenticated user
            ->where('users.id', '!=', $currentUserId)
            // 2. Exclude Super Admin / administrative accounts
            ->where(function ($q) {
                $q->whereNull('users.is_admin')
                    ->orWhere('users.is_admin', false);
            })
            // 3. Exclude dedicated Jury Panel accounts
            ->whereDoesntHave('juryPanel')
            // 4. Match public profile name or username/slug
            ->whereHas('profile', function ($q) use ($clean) {
                $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
                $concatSql = $driver === 'sqlite'
                    ? "(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?"
                    : "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?";

                $q->where('first_name', 'LIKE', "%{$clean}%")
                    ->orWhere('last_name', 'LIKE', "%{$clean}%")
                    ->orWhere('slug', 'LIKE', "%{$clean}%")
                    ->orWhereRaw($concatSql, ["%{$clean}%"]);
            })
            ->limit($cappedLimit)
            ->get();
    }

    /**
     * Validate whether a user is eligible to be named as a case respondent.
     *
     * @throws ValidationException
     */
    public function validateEligibility(int $respondentId, int $currentUserId): User
    {
        /** @var User|null $respondent */
        $respondent = User::with('juryPanel')->find($respondentId);

        if (!$respondent) {
            throw ValidationException::withMessages([
                'respondent_id' => ['The selected respondent account does not exist.'],
            ]);
        }

        if ($respondent->id === $currentUserId) {
            throw ValidationException::withMessages([
                'respondent_id' => ['You cannot file a Tribunal case against yourself.'],
            ]);
        }

        if ($respondent->isJuryPanelAccount()) {
            throw ValidationException::withMessages([
                'respondent_id' => ['Dedicated Jury Panel accounts cannot be named as a case respondent.'],
            ]);
        }

        if ($respondent->isAdmin()) {
            throw ValidationException::withMessages([
                'respondent_id' => ['Administrative and system management accounts cannot be named as a case respondent.'],
            ]);
        }

        return $respondent;
    }
}
