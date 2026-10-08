<?php

namespace App\Imports;

use App\Models\profession;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use RuntimeException;

class QuestionsImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $professionId = profession::query()
            ->where('name', 'today special questions')
            ->value('id');

        if (!$professionId) {
            throw new RuntimeException('Seed professions before importing the question bank.');
        }

        $sheets = [
            '1 Core_Intelligence_Self_Awaren' => 'Core Intelligence',
            'Emotional Intelligence & Empath' => 'Emotional Intelligence',
            '3 Civic_Social_Awareness_Quiz_' => 'Civic Awareness',
            '4 Leadership & Decision-Making' => 'Leadership',
            '5 Cultural & Global Awareness' => 'Cultural Awareness',
            '6 Quality Management System(QMS' => 'QMS',
        ];

        $imports = [];
        foreach ($sheets as $sheetName => $category) {
            $imports[$sheetName] = new QuestionSheetImport($category, $professionId);
        }

        return $imports;
    }
}
