<?php

namespace App\Services;

use App\Models\User;

/**
 * HIP rank tiers, relative to the spread of LCI scores across all users:
 *   x = highest LCI, y = lowest LCI, range = x - y
 *   percent below max = (x - lci) / range * 100
 * Each tier covers [lower, upper) percent below the top score.
 */
class HipRankMatrix
{
    public const COLORS = [
        'Platinum' => '#E5E4E2',
        'Gold' => '#FFD700',
        'Silver' => '#C0C0C0',
        'Bronze' => '#CD7F32',
    ];

    /** [upper bound (% below max, exclusive), rank name, tier] in order from the top. */
    private const TIERS = [
        [5, 'Platinum 1', 'Platinum'],
        [7, 'Platinum 2', 'Platinum'],
        [10, 'Platinum 3', 'Platinum'],
        [12, 'Gold 1', 'Gold'],
        [15, 'Gold 2', 'Gold'],
        [17, 'Gold 3', 'Gold'],
        [20, 'Gold 4', 'Gold'],
        // 20%–50% split evenly into 8 Silver bands of 3.75% each.
        [23.75, 'Silver 1', 'Silver'],
        [27.5, 'Silver 2', 'Silver'],
        [31.25, 'Silver 3', 'Silver'],
        [35, 'Silver 4', 'Silver'],
        [38.75, 'Silver 5', 'Silver'],
        [42.5, 'Silver 6', 'Silver'],
        [46.25, 'Silver 7', 'Silver'],
        [50, 'Silver 8', 'Silver'],
        [70, 'Bronze 1', 'Bronze'],
        // The mark sheet stops at 70%; everyone further below the top falls in Bronze 2.
        [PHP_FLOAT_MAX, 'Bronze 2', 'Bronze'],
    ];

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
        // Everyone level (or a single user): nobody is below the top.
        $percentBelowMax = $range > 0 ? max(0.0, ($highest - $lci) / $range * 100) : 0.0;

        foreach (self::TIERS as [$upper, $name, $tier]) {
            if ($percentBelowMax < $upper) {
                return [
                    'hip_rank' => $name,
                    'rank_tier' => $tier,
                    'rank_badge_color' => self::COLORS[$tier],
                    'percent_below_max' => round($percentBelowMax, 2),
                ];
            }
        }

        // Unreachable: the last tier is unbounded.
        return ['hip_rank' => 'Bronze 2', 'rank_tier' => 'Bronze', 'rank_badge_color' => self::COLORS['Bronze'], 'percent_below_max' => 100.0];
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
