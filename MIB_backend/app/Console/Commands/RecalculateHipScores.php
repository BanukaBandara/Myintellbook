<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\HipScoreCalculator;
use Illuminate\Console\Command;

class RecalculateHipScores extends Command
{
    protected $signature = 'hip:recalculate-all';

    protected $description = 'Recalculate HIP scores for all users';

    public function handle(): int
    {
        $recalculated = 0;

        User::query()->chunkById(200, function ($users) use (&$recalculated): void {
            foreach ($users as $user) {
                HipScoreCalculator::recalculate($user);
                $recalculated++;
            }
        });

        $this->info("Recalculated HIP scores for {$recalculated} users.");

        return self::SUCCESS;
    }
}
