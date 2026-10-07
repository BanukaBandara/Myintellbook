<?php

namespace App\Console\Commands;

use App\Models\Answer;
use App\Models\User;
use App\Models\UserAnswer;
use App\Services\HipScoreCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReopenInstantScoredDailyAnswers extends Command
{
    protected $signature = 'app:reopen-instant-scored-daily-answers {--dry-run : List affected answers without changing them}';

    protected $description = 'Return today\'s answers that were scored on submission to pending evaluation and reverse their points';

    public function handle(): int
    {
        // The midnight run only scores earlier days, so an answer from today that is already
        // evaluated can only have been scored instantly by the old submission flow.
        $answers = UserAnswer::query()
            ->where('status', UserAnswer::STATUS_EVALUATED)
            ->whereDate('answer_date', today())
            ->get();

        if ($answers->isEmpty()) {
            $this->info('No instantly scored answers from today.');
            return self::SUCCESS;
        }

        foreach ($answers as $answer) {
            $this->line("Answer {$answer->id}: user {$answer->user_id}, question {$answer->question_id}, score {$answer->score}");

            if ($this->option('dry-run')) {
                continue;
            }

            DB::transaction(function () use ($answer): void {
                $user = User::query()->lockForUpdate()->findOrFail($answer->user_id);
                $locked = UserAnswer::query()->lockForUpdate()->findOrFail($answer->id);
                if ($locked->status !== UserAnswer::STATUS_EVALUATED) {
                    return;
                }

                $user->total_points = max(0, (float) $user->total_points - (float) $locked->score);
                $user->save();

                $locked->update([
                    'score' => 0,
                    'is_correct' => null,
                    'status' => UserAnswer::STATUS_PENDING,
                    'evaluated_at' => null,
                ]);

                // DailyAnswerEvaluator recreates this mirror when the midnight run scores the answer.
                Answer::query()
                    ->where('user_id', $user->id)
                    ->where('question_id', $locked->question_id)
                    ->delete();

                HipScoreCalculator::recalculate($user);
            });
        }

        $this->info(($this->option('dry-run') ? 'Would reopen ' : 'Reopened ').$answers->count().' answer(s).');

        return self::SUCCESS;
    }
}
