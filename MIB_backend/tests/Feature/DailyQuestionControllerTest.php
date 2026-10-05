<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\Question;
use App\Models\User;
use App\Models\profession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DailyQuestionControllerTest extends TestCase
{
    use RefreshDatabase;

    private array $authHeaders = [];

    #[Test]
    public function it_returns_scheduled_question_without_revealing_option_scores(): void
    {
        $user = $this->createUserWithToken();
        $profession = $this->createProfession();
        $question = Question::create([
            'profession_id' => $profession->id,
            'category' => 'Core Intelligence',
            'question' => 'Which choice is best?',
            'options' => [
                ['text' => 'Option A', 'score' => 5],
                ['text' => 'Option B', 'score' => 0],
            ],
            'scheduled_date' => today(),
            'is_used' => true,
        ]);

        Answer::create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'answer' => 'Option B',
            'answer_status' => 'incorrect',
        ]);
        DB::table('scoring_items')->insert([
            'id' => 7,
            'name' => 'Daily question',
            'base_score' => 7,
            'rule_type' => 'fixed',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_scores')->insert([
            'user_id' => $user->id,
            'scoring_item_id' => 7,
            'source_type' => Question::class,
            'source_id' => (string) $question->id,
            'input_value' => '5',
            'calculated_score' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders($this->authHeaders)
            ->getJson('/api/daily-question/today')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('has_answered', true)
            ->assertJsonPath('user_score_today', 5)
            ->assertJsonPath('question.id', $question->id)
            ->assertJsonPath('question.options', ['Option A', 'Option B'])
            ->assertJsonMissingPath('question.answer');
    }

    #[Test]
    public function fallback_question_is_selected_deterministically_from_active_questions(): void
    {
        $user = $this->createUserWithToken();
        $profession = $this->createProfession();
        $questions = collect(range(1, 4))->map(fn ($number) => Question::create([
            'profession_id' => $profession->id,
            'category' => 'Civic Awareness',
            'question' => "Question {$number}",
            'options' => [['text' => 'Only option', 'score' => 1]],
            'is_used' => $number !== 2,
        ]));

        $activeQuestions = $questions->where('is_used', false)->sortBy('id')->values();
        $expectedQuestion = $activeQuestions[today()->dayOfYear % $activeQuestions->count()];

        $this->withHeaders($this->authHeaders)
            ->getJson('/api/daily-question/today')
            ->assertOk()
            ->assertJsonPath('has_answered', false)
            ->assertJsonPath('user_score_today', null)
            ->assertJsonPath('question.id', $expectedQuestion->id)
            ->assertJsonPath('question.options', ['Only option']);
    }

    #[Test]
    public function endpoint_requires_authentication(): void
    {
        $this->getJson('/api/daily-question/today')->assertUnauthorized();
    }

    private function createUserWithToken(): User
    {
        $user = User::create([
            'email' => Str::lower(Str::random(12)).'@example.com',
            'password' => 'password123',
        ]);
        $rawToken = Str::random(40);

        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $rawToken),
            'expires_at' => now()->addDay(),
        ]);

        $this->authHeaders = [
            'Authorization' => "Bearer {$rawToken}",
            'Accept' => 'application/json',
        ];

        return $user;
    }

    private function createProfession(): profession
    {
        $category = Category::create(['name' => 'Daily Question Test']);

        return profession::create([
            'category_id' => $category->id,
            'name' => 'Daily Question Test',
        ]);
    }
}
