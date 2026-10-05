<?php

namespace App\Support;

use App\Models\Question;
use Illuminate\Support\Collection;

final class QuestionOptions
{
    /**
     * A question's options as [{text, points, is_correct}], keeping the stored order so the
     * index can be used as the answer id. Accepts both {text, score} and legacy {value, marks}.
     *
     * @return Collection<int, array{text: string, points: float, is_correct: bool}>
     */
    public static function normalize(Question $question): Collection
    {
        $options = collect(is_array($question->options) ? array_values($question->options) : [])
            ->map(function ($option) {
                if (!is_array($option)) {
                    return null;
                }

                $text = $option['text'] ?? $option['value'] ?? null;
                $points = $option['score'] ?? $option['marks'] ?? null;

                return is_string($text) && trim($text) !== '' && is_numeric($points)
                    ? ['text' => $text, 'points' => (float) $points]
                    : null;
            });

        // Invalid entries are dropped without renumbering, so a valid option keeps its stored index.
        $valid = $options->filter();
        $maxPoints = $valid->max('points');

        return $valid->map(fn (array $option) => [
            ...$option,
            'is_correct' => $option['points'] === $maxPoints,
        ]);
    }
}
