<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\HipRankMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LciRankTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The HIP matrix sheet: [% of range below x where the band ends, rank, colour].
     * Each band is x - range*prev% (exclusive) down to x - range*this% (inclusive).
     */
    private const SHEET = [
        [5, 'Platinum 1', '#E5E4E2'],
        [7, 'Platinum 2', '#E5E4E2'],
        [10, 'Platinum 3', '#E5E4E2'],
        [12, 'Gold 1', '#FFD700'],
        [15, 'Gold 2', '#FFD700'],
        [17, 'Gold 3', '#FFD700'],
        [20, 'Gold 4', '#FFD700'],
        [24, 'Silver 1', '#C0C0C0'],
        [28, 'Silver 2', '#C0C0C0'],
        [32, 'Silver 3', '#C0C0C0'],
        [36, 'Silver 4', '#C0C0C0'],
        [40, 'Silver 5', '#C0C0C0'],
        [44, 'Silver 6', '#C0C0C0'],
        [48, 'Silver 7', '#C0C0C0'],
        [50, 'Silver 8', '#C0C0C0'],
        [70, 'Bronze 1', '#CD7F32'],
    ];

    /**
     * Every boundary of the sheet, on a round range and on an awkward decimal one: a score exactly on
     * x - range*p% is still in the band above (>=); 0.01 lower falls into the next band (<).
     */
    public static function boundaries(): array
    {
        $cases = [];
        foreach ([[1000.0, 0.0], [8765.43, 1234.56]] as [$x, $y]) {
            $range = $x - $y;
            foreach (self::SHEET as $i => [$percent, $rank, $color]) {
                // Exact decimal threshold (bcmath, independent of the implementation). Scores have 2
                // decimals, so the lowest score meeting ">= threshold" is the threshold rounded up to cents.
                $threshold = bcsub((string) $x, bcdiv(bcmul((string) $range, (string) $percent, 4), '100', 4), 4);
                $cents = bcadd($threshold, '0', 2);
                $boundary = (float) (bccomp($threshold, $cents, 4) === 0 ? $cents : bcadd($cents, '0.01', 2));
                [, $nextRank, $nextColor] = self::SHEET[$i + 1] ?? [null, 'Bronze 2', '#CD7F32'];

                $cases["{$rank} at exactly {$percent}% (x={$x})"] = [$boundary, $x, $y, $rank, $color];
                $cases["{$nextRank} just under {$percent}% (x={$x})"] = [round($boundary - 0.01, 2), $x, $y, $nextRank, $nextColor];
            }
            $cases["Platinum 1 at the top score (x={$x})"] = [$x, $x, $y, 'Platinum 1', '#E5E4E2'];
            $cases["Bronze 2 at the lowest score (x={$x})"] = [$y, $x, $y, 'Bronze 2', '#CD7F32'];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('boundaries')]
    public function rank_boundaries_match_the_hip_matrix_exactly(float $lci, float $x, float $y, string $rank, string $color): void
    {
        $result = HipRankMatrix::rankFor($lci, $x, $y);

        $this->assertSame($rank, $result['hip_rank'], "LCI {$lci} with x={$x}, y={$y}");
        $this->assertSame($color, $result['rank_badge_color']);
    }

    #[Test]
    public function the_range_is_relative_to_the_lowest_score_not_zero(): void
    {
        // x = 30000, y = 20000, range 10000: 29000 is exactly x - range*10% → still Platinum 3.
        $this->assertSame('Platinum 3', HipRankMatrix::rankFor(29000, 30000, 20000)['hip_rank']);
        $this->assertSame('Gold 1', HipRankMatrix::rankFor(28999.99, 30000, 20000)['hip_rank']);
    }

    #[Test]
    public function no_spread_assigns_the_base_rank_instead_of_platinum(): void
    {
        // Single user or everyone tied.
        $this->assertSame('Bronze 1', HipRankMatrix::rankFor(500, 500, 500)['hip_rank']);
        $this->assertSame('Bronze 1', HipRankMatrix::rankFor(0, 0, 0)['hip_rank']);
        // Defensive: inverted bounds are treated the same way.
        $this->assertSame('Bronze 1', HipRankMatrix::rankFor(10, 5, 20)['hip_rank']);
    }

    #[Test]
    public function bounds_are_the_max_and_min_hip_score_of_all_users(): void
    {
        foreach ([120.5, 4400.25, 87, 0.75] as $i => $score) {
            User::create(['email' => "u{$i}@example.com", 'password' => 'password123', 'hip_score' => $score]);
        }

        $this->assertSame(
            ['highest' => (float) User::max('hip_score'), 'lowest' => (float) User::min('hip_score')],
            HipRankMatrix::bounds(),
        );
        $this->assertSame(['highest' => 4400.25, 'lowest' => 0.75], HipRankMatrix::bounds());
    }

    #[Test]
    public function a_single_user_system_gets_the_base_rank_from_the_endpoint(): void
    {
        $user = User::create(['email' => 'solo@example.com', 'password' => 'password123']);
        $raw = Str::random(40);
        ApiToken::create(['user_id' => $user->id, 'token' => hash('sha256', $raw), 'expires_at' => now()->addDay()]);

        $this->withHeaders(['Authorization' => "Bearer {$raw}", 'Accept' => 'application/json'])
            ->getJson('/api/scores/lci')
            ->assertOk()
            ->assertJsonPath('hip_rank', 'Bronze 1');
    }

    #[Test]
    public function the_lci_endpoint_returns_score_rank_and_breakdown(): void
    {
        User::create(['email' => 'top@example.com', 'password' => 'password123', 'hip_score' => 1000]);
        User::create(['email' => 'low@example.com', 'password' => 'password123', 'hip_score' => 0]);
        $user = User::create(['email' => Str::random(8).'@example.com', 'password' => 'password123']);
        $raw = Str::random(40);
        ApiToken::create(['user_id' => $user->id, 'token' => hash('sha256', $raw), 'expires_at' => now()->addDay()]);

        $this->withHeaders(['Authorization' => "Bearer {$raw}", 'Accept' => 'application/json'])
            ->getJson('/api/scores/lci')
            ->assertOk()
            ->assertJsonPath('lci_score', 0)
            ->assertJsonPath('hip_rank', 'Bronze 2')
            ->assertJsonPath('rank_badge_color', '#CD7F32')
            ->assertJsonPath('highest_lci', 1000)
            ->assertJsonCount(7, 'breakdown')
            ->assertJsonPath('breakdown.0.key', 'tqm')
            ->assertJsonPath('breakdown.1.key', 'em');
    }
}
