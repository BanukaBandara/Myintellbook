<?php

namespace App\Services;

use App\Models\User;

/**
 * HIP rank tiers from the HIP matrix sheet, relative to the spread of LCI scores across all users:
 *   x = highest LCI, y = lowest LCI, range = x - y
 * A tier with lower-bound fraction p is reached when lci >= x - range * p, so a score exactly on a
 * boundary takes the higher rank (Platinum 1 is lci >= x - range*0.05, Platinum 2 is
 * lci < x - range*0.05 and >= x - range*0.07, and so on).
 */
class HipRankMatrix
{
    public const COLORS = [
        'Platinum' => '#E5E4E2',
        'Gold' => '#FFD700',
        'Silver' => '#C0C0C0',
        'Bronze' => '#CD7F32',
    ];

    /** Given when there is no spread to rank against (a single user, or everyone tied). */
    private const BASE_RANK = ['Bronze 1', 'Bronze'];

    /** [fraction of range below x (inclusive lower score bound), rank name, tier] in order from the top. */
    private const TIERS = [
        [0.05, 'Platinum 1', 'Platinum'],
        [0.07, 'Platinum 2', 'Platinum'],
        [0.10, 'Platinum 3', 'Platinum'],
        [0.12, 'Gold 1', 'Gold'],
        [0.15, 'Gold 2', 'Gold'],
        [0.17, 'Gold 3', 'Gold'],
        [0.20, 'Gold 4', 'Gold'],
        [0.24, 'Silver 1', 'Silver'],
        [0.28, 'Silver 2', 'Silver'],
        [0.32, 'Silver 3', 'Silver'],
        [0.36, 'Silver 4', 'Silver'],
        [0.40, 'Silver 5', 'Silver'],
        [0.44, 'Silver 6', 'Silver'],
        [0.48, 'Silver 7', 'Silver'],
        [0.50, 'Silver 8', 'Silver'],
        [0.70, 'Bronze 1', 'Bronze'],
    ];

    /** The sheet stops at 70% below the top; scores below that fall in Bronze 2. */
    private const BELOW_SHEET_RANK = ['Bronze 2', 'Bronze'];

    /**
     * @return array{highest: float, lowest: float}
     */
    public static function bounds(): array
    {
        $row = User::query()->selectRaw('MAX(hip_score) as highest, MIN(hip_score) as lowest')->first();

        return [
            'highest' => (float) ($row->highest ?? 0),
            'lowest' => (float) ($row->lowest ?? 0),
        ];
    }

    /**
     * @return array{hip_rank: string, rank_tier: string, rank_badge_color: string, percent_below_max: float}
     */
    public static function rankFor(float $lci, float $highest, float $lowest): array
    {
        $range = $highest - $lowest;

        // No spread (single user, initial seed data, everyone tied): nothing to rank against.
        if ($range <= 0) {
            return self::result(...self::BASE_RANK, percentBelowMax: 0.0);
        }

        $percentBelowMax = max(0.0, ($highest - $lci) / $range * 100);

        // Compared in score space, exactly as the sheet writes it: lci >= x - range * p.
        // Scores have 2 decimals, so thresholds are exact to 4; rounding to 6 strips float noise
        // (1000 - 1000 * 0.15 is 850.0000000000001) that would push boundary scores down a rank.
        foreach (self::TIERS as [$fraction, $name, $tier]) {
            if ($lci >= round($highest - $range * $fraction, 6)) {
                return self::result($name, $tier, $percentBelowMax);
            }
        }

        return self::result(...self::BELOW_SHEET_RANK, percentBelowMax: $percentBelowMax);
    }

    /**
     * @return array{hip_rank: string, rank_tier: string, rank_badge_color: string, percent_below_max: float}
     */
    private static function result(string $name, string $tier, float $percentBelowMax): array
    {
        return [
            'hip_rank' => $name,
            'rank_tier' => $tier,
            'rank_badge_color' => self::COLORS[$tier],
            'percent_below_max' => round($percentBelowMax, 2),
        ];
    }

    /**
     * Fresh LCI for the user plus their rank and per-bucket breakdown.
     */
    public static function forUser(User $user): array
    {
        $breakdown = HipScoreCalculator::breakdown($user);
        $lci = round(array_sum($breakdown), 2);
        if ((float) $user->hip_score !== $lci) {
            $user->hip_score = $lci;
            $user->save();
        }

        ['highest' => $highest, 'lowest' => $lowest] = self::bounds();

        return [
            'lci_score' => $lci,
            ...self::rankFor($lci, $highest, $lowest),
            'highest_lci' => $highest,
            'lowest_lci' => $lowest,
            'breakdown' => array_map(
                fn (string $key, string $label) => ['key' => $key, 'label' => $label, 'score' => round($breakdown[$key], 2)],
                array_keys(HipScoreCalculator::LCI_CATEGORIES),
                HipScoreCalculator::LCI_CATEGORIES,
            ),
        ];
    }
}
