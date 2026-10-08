<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use App\Models\UserAnswer;
use Illuminate\Support\Facades\DB;

class DailyAnswerEvaluator
{
    public const CORRECT_SCORE = 5.0;

    /**
     * Returns the option at the given index when it carries a valid server-side score.
     *
     * @return array{text: string, score: float}|null
     */
    public static function optionAt(Question $question, int $index): ?array
    {
        $option = is_array($question->options)
            ? (array_values($question->options)[$index] ?? null)
            : null;

        if (!is_array($option)
            || !isset($option['text'], $option['score'])
            || !is_numeric($option['score'])) {
            return null;
        }

        $score = (float) $option['score'];
        if ($score < 0 || $score > self::CORRECT_SCORE) {
            return null;
        }

        return ['text' => (string) $option['text'], 'score' => $score];
    }

    /**
     * Scores a pending answer, awards the points and recalculates the user's HIP score.
     * Returns false when the answer was already evaluated by another process.
     */
    public static function evaluate(int $userAnswerId): bool
    {
        return DB::transaction(function () use ($userAnswerId): bool {
            $answer = UserAnswer::query()->lockForUpdate()->find($userAnswerId);
            if (!$answer || $answer->status !== UserAnswer::STATUS_PENDING) {
                return false;
            }

            $user = User::query()->lockForUpdate()->findOrFail($answer->user_id);
            $question = Question::query()->find($answer->question_id);
            $option = $question ? self::optionAt($question, $answer->selected_option_index) : null;

            // An option that no longer validates (question edited/removed) earns nothing.
            $score = $option['score'] ?? 0.0;
            $isCorrect = $option !== null && $score >= self::CORRECT_SCORE;

            $answer->update([
                'score' => $score,
                'is_correct' => $isCorrect,
                'status' => UserAnswer::STATUS_EVALUATED,
                'evaluated_at' => now(),
            ]);

            // Mirror into the legacy answers table, which DailyPost and other screens still read.
            if ($question) {
                Answer::query()->updateOrCreate(
                    ['user_id' => $user->id, 'question_id' => $question->id],
                    [
                        'selected_option_index' => $answer->selected_option_index,
                        'answer' => $option['text'] ?? '',
                        'answer_status' => $isCorrect ? 'correct' : 'incorrect',
                        'score' => $score,
                    ],
                );
            }

            $user->total_points = (float) $user->total_points + $score;
            $user->save();
            HipScoreCalculator::recalculate($user);

            return true;
        }, 3);
    }
}
