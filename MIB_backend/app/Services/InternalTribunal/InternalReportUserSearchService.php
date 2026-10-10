<?php

namespace App\Services\InternalTribunal;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class InternalReportUserSearchService
{
    public const MIN_QUERY_LENGTH = 3;
    public const MAX_SEARCH_LIMIT = 20;
    public const DEFAULT_SEARCH_LIMIT = 10;

    /**
     * Search platform users eligible for internal misconduct reporting.
     * Excludes self and Super Admins.
     */
    public function search(string $query, int $currentUserId, int $limit = self::DEFAULT_SEARCH_LIMIT): Collection
    {
        $clean = trim($query);

        if (mb_strlen($clean) < self::MIN_QUERY_LENGTH) {
            return new Collection();
        }

        $cappedLimit = min(max($limit, 1), self::MAX_SEARCH_LIMIT);

        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $concatSql = $driver === 'sqlite'
            ? "(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?"
            : "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?";

        return User::query()
            ->with(['profile', 'juryPanel'])
            ->where('users.id', '!=', $currentUserId)
            ->where(function ($q) {
                $q->whereNull('users.is_admin')
                    ->orWhere('users.is_admin', false);
            })
            ->where(function ($query) use ($clean, $concatSql) {
                $query->whereHas('profile', function ($q) use ($clean, $concatSql) {
                    $q->where('first_name', 'like', "%{$clean}%")
                        ->orWhere('last_name', 'like', "%{$clean}%")
                        ->orWhere('slug', 'like', "%{$clean}%")
                        ->orWhereRaw($concatSql, ["%{$clean}%"]);
                });
                // Deliberately no email matching: typing an address would reveal whose account it is.
            })
            ->limit($cappedLimit)
            ->get();
    }
}
