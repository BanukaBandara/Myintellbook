<?php

namespace App\Imports;

use App\Models\Question;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class QuestionsImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
         return new Question([
            'question'   => $row['question'],
            'options'    => json_encode([
                'A' => $row['option_a'],
                'B' => $row['option_b'],
                'C' => $row['option_c'],
                'D' => $row['option_d'],
            ]),
            'answer'     => $row['answer'],
            'difficulty_level' => $row['difficulty_level'],
            'profession_id'   => $row['category'],
            'is_used'    => $row['is_used'] ?? false, // Default to false if not provided
        ]);
    }
}
