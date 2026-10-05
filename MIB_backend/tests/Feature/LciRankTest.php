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

    public static function tiers(): array
    {
        // Range 0..1000, so LCI = 1000 - (percent below max * 10).
        return [
            'top score' => [1000, 'Platinum 1', '#E5E4E2'],
            '4.9% below' => [951, 'Platinum 1', '#E5E4E2'],
            '5% below' => [950, 'Platinum 2', '#E5E4E2'],
            '8% below' => [920, 'Platinum 3', '#E5E4E2'],
            '10% below' => [900, 'Gold 1', '#FFD700'],
            '13% below' => [870, 'Gold 2', '#FFD700'],
            '16% below' => [840, 'Gold 3', '#FFD700'],
            '19% below' => [810, 'Gold 4', '#FFD700'],
            '20% below' => [800, 'Silver 1', '#C0C0C0'],
            '30% below' => [700, 'Silver 3', '#C0C0C0'],
            '49% below' => [510, 'Silver 8', '#C0C0C0'],
            '50% below' => [500, 'Bronze 1', '#CD7F32'],
            '69% below' => [310, 'Bronze 1', '#CD7F32'],
            '70% below' => [300, 'Bronze 2', '#CD7F32'],
            'lowest score' => [0, 'Bronze 2', '#CD7F32'],
        ];
    }

    #[Test]
    #[DataProvider('tiers')]
    public function rank_tiers_follow_the_percent_below_the_highest_lci(float $lci, string $rank, string $color): void
    {
        $result = HipRankMatrix::rankFor($lci, 1000, 0);

        $this->assertSame($rank, $result['hip_rank']);
        $this->assertSame($color, $result['rank_badge_color']);
    }

    #[Test]
    public function the_range_is_relative_to_the_lowest_score_not_zero(): void
    {
        // x = 30000, y = 20000: 29000 is 10% below max → Gold 1.
        $this->assertSame('Gold 1', HipRankMatrix::rankFor(29000, 30000, 20000)['hip_rank']);
    }

    #[Test]
    public function a_single_user_or_a_tied_system_ranks_everyone_at_the_top(): void
    {
        $this->assertSame('Platinum 1', HipRankMatrix::rankFor(500, 500, 500)['hip_rank']);
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
