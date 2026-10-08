<?php

namespace App\Console\Commands;

use App\Models\UserAnswer;
use App\Services\DailyAnswerEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EvaluateDailyQuestions extends Command
{
    protected $signature = 'app:evaluate-daily-questions';

    protected $description = 'Score pending daily question answers from previous days and award points';

    public function handle(): int
    {
        $evaluated = 0;
        $failed = 0;

        // Everything before today: normally just yesterday, but also catches up after a missed run.
        UserAnswer::query()
            ->where('status', UserAnswer::STATUS_PENDING)
            ->whereDate('answer_date', '<', today())
            ->select('id')
            ->chunkById(200, function ($answers) use (&$evaluated, &$failed): void {
                foreach ($answers as $answer) {
                    try {
                        if (DailyAnswerEvaluator::evaluate($answer->id)) {
                            $evaluated++;
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error("EvaluateDailyQuestions: answer {$answer->id} failed: {$e->getMessage()}");
                    }
                }
            });

        Log::info("EvaluateDailyQuestions evaluated {$evaluated} answers ({$failed} failed) at ".now());
        $this->info("Evaluated {$evaluated} daily answers, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
