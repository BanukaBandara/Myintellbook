<?php

namespace App\Services;

use App\Models\Question;

class DailyQuestionResolver
{
    /**
     * Today's daily question: the one scheduled for today, otherwise a deterministic
     * pick from the unused pool that rotates by day of year.
     */
    public static function today(): ?Question
    {
        $question = Question::query()
            ->with('profession:id,name')
            ->whereDate('scheduled_date', today())
            ->orderBy('id')
            ->first();

        if (!$question) {
            $activeQuestions = Question::query()->where('is_used', false);
            $totalQuestions = (clone $activeQuestions)->count();

            if ($totalQuestions > 0) {
                $offset = today()->dayOfYear % $totalQuestions;
                $question = $activeQuestions
                    ->with('profession:id,name')
                    ->orderBy('id')
                    ->skip($offset)
                    ->first();
            }
        }

        return $question;
    }
}
