<?php

namespace Tests\Feature;

use App\Imports\QuestionsImport;
use App\Models\Category;
use App\Models\Question;
use App\Models\profession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuestionBankImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function imports_scored_options_from_the_six_question_sheets(): void
    {
        $category = Category::create(['name' => 'Daily Questions Import Test']);
        profession::create([
            'category_id' => $category->id,
            'name' => 'today special questions',
        ]);

        $path = storage_path('app/imports/MyIntellibook_Question_Bank_v2.0.xlsx');
        $this->assertFileExists($path);

        Excel::import(new QuestionsImport(), $path);

        foreach ([
            'Core Intelligence',
            'Emotional Intelligence',
            'Civic Awareness',
            'Leadership',
            'Cultural Awareness',
            'QMS',
        ] as $sheetCategory) {
            $this->assertGreaterThan(0, Question::where('category', $sheetCategory)->count());
        }

        $question = Question::where('category', 'Core Intelligence')->firstOrFail();
        $this->assertSame(
            'When you feel angry during a workplace argument, what is the most emotionally intelligent response?',
            $question->question
        );
        $this->assertSame([
            'text' => 'Shout louder to prove your point',
            'score' => 0,
        ], $question->options[0]);
        $this->assertCount(5, $question->options);
    }
}
