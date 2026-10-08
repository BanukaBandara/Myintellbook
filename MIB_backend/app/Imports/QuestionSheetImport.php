<?php

namespace App\Imports;

use App\Models\Question;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;

class QuestionSheetImport implements OnEachRow, WithStartRow
{
    public function __construct(
        private readonly string $category,
        private readonly int $professionId,
    ) {
    }

    public function startRow(): int
    {
        return 3;
    }

    public function onRow(Row $row): void
    {
        $values = $row->toArray();
        $questionText = trim((string) ($values[1] ?? ''));
        if ($questionText === '') {
            return;
        }

        $options = [];
        for ($index = 0; $index < 5; $index++) {
            $text = trim((string) ($values[2 + ($index * 2)] ?? ''));
            $rawScore = $values[3 + ($index * 2)] ?? null;

            if ($text === '' || !is_numeric($rawScore)) {
                throw new InvalidArgumentException("Question bank row has an invalid option or score for {$questionText}.");
            }

            $score = (float) $rawScore;
            if ($score < 0 || $score > 5) {
                throw new InvalidArgumentException("Question bank score must be between 0 and 5 for {$questionText}.");
            }

            $options[] = [
                'text' => $text,
                'score' => floor($score) === $score ? (int) $score : $score,
            ];
        }

        $question = Question::firstOrNew([
            'question' => $questionText,
            'category' => $this->category,
        ]);

        $question->fill([
            'profession_id' => $this->professionId,
            'options' => $options,
            'answer' => null,
            'difficulty_level' => null,
            'is_used' => false,
        ]);

        $question->save();
    }
}
