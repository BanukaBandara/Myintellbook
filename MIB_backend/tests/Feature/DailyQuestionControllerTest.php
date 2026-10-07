<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\Question;
use App\Models\User;
use App\Models\UserAnswer;
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
    public function answers_stay_pending_and_editable_until_the_midnight_evaluation(): void
    {
        $user = $this->createUserWithToken();
        $question = Question::create([
            'profession_id' => $this->createProfession()->id,
            'category' => 'Core Intelligence',
            'question' => 'Which choice is best?',
            'options' => [
                ['text' => 'Option A', 'score' => 5],
                ['text' => 'Option B', 'score' => 0],
            ],
            'scheduled_date' => today(),
            'is_used' => true,
        ]);

        $this->withHeaders($this->authHeaders)
            ->postJson('/api/daily-questions/answer', ['question_id' => $question->id, 'selected_option_index' => 1])
            ->assertOk()
            ->assertJsonPath('answer_status', UserAnswer::STATUS_PENDING)
            ->assertJsonPath('points', null)
            ->assertJsonPath('is_correct', null);

        // Re-submitting before midnight overwrites the same attempt.
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/daily-questions/answer', ['question_id' => $question->id, 'selected_option_index' => 0])
            ->assertOk()
            ->assertJsonPath('answer_status', UserAnswer::STATUS_PENDING);

        $this->withHeaders($this->authHeaders)
            ->getJson('/api/daily-question/today')
            ->assertOk()
            ->assertJsonPath('answer_status', UserAnswer::STATUS_PENDING)
            ->assertJsonPath('can_update', true)
            ->assertJsonPath('selected_option_index', 0)
            ->assertJsonPath('user_score_today', null);

        $answer = UserAnswer::query()->where('user_id', $user->id)->sole();
        $this->assertSame(0, $answer->selected_option_index);
        $this->assertSame(0.0, (float) $user->fresh()->total_points);

        // Midnight: the evaluation run scores the final choice from the previous day.
        $this->travelTo(today()->addDay()->startOfDay());
        $this->artisan('app:evaluate-daily-questions')->assertSuccessful();

        $answer->refresh();
        $this->assertSame(UserAnswer::STATUS_EVALUATED, $answer->status);
        $this->assertTrue($answer->is_correct);
        $this->assertSame(5.0, $answer->score);
        $this->assertSame(5.0, (float) $user->fresh()->total_points);
    }

    #[Test]
    public function instantly_scored_answers_from_today_can_be_reopened(): void
    {
        $user = $this->createUserWithToken();
        $user->update(['total_points' => 12]);
        $question = Question::create([
            'profession_id' => $this->createProfession()->id,
            'question' => 'Which choice is best?',
            'options' => [['text' => 'Option A', 'score' => 5]],
            'scheduled_date' => today(),
            'is_used' => true,
        ]);
        $answer = UserAnswer::create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'selected_option_index' => 0,
            'answer_date' => today(),
            'score' => 5,
            'is_correct' => true,
            'status' => UserAnswer::STATUS_EVALUATED,
            'evaluated_at' => now(),
        ]);
        Answer::create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'answer' => 'Option A',
            'answer_status' => 'correct',
            'score' => 5,
        ]);

        $this->artisan('app:reopen-instant-scored-daily-answers')->assertSuccessful();

        $answer->refresh();
        $this->assertSame(UserAnswer::STATUS_PENDING, $answer->status);
        $this->assertNull($answer->is_correct);
        $this->assertSame(7.0, (float) $user->fresh()->total_points);
        $this->assertFalse(Answer::query()->where('question_id', $question->id)->exists());

        $this->withHeaders($this->authHeaders)
            ->getJson('/api/daily-question/today')
            ->assertJsonPath('answer_status', UserAnswer::STATUS_PENDING)
            ->assertJsonPath('can_update', true)
            ->assertJsonPath('user_score_today', null)
            ->assertJsonPath('is_correct', null);
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
