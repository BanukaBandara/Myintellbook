<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Category;
use App\Models\Question;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\profession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DailyAnswerSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private array $headers;
    private profession $profession;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(now()->setTime(10, 0));

        $this->user = User::create([
            'email' => Str::lower(Str::random(12)).'@example.com',
            'password' => 'password123',
        ]);
        $rawToken = Str::random(40);
        ApiToken::create([
            'user_id' => $this->user->id,
            'token' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(3),
        ]);
        $this->headers = [
            'Authorization' => "Bearer {$rawToken}",
            'Accept' => 'application/json',
        ];

        $category = Category::create(['name' => 'Daily Questions Test']);
        $this->profession = profession::create([
            'category_id' => $category->id,
            'name' => 'Daily Questions Test',
        ]);
    }

    #[Test]
    public function answers_are_scored_on_submission_and_cannot_be_changed_afterwards(): void
    {
        $question = $this->question(today());

        // Clients cannot supply their own score.
        $this->withHeaders($this->headers)
            ->postJson('/api/daily-questions/answer', [
                'question_id' => $question->id,
                'selected_option_index' => 0,
                'score' => 5,
            ])
            ->assertUnprocessable();

        $this->withHeaders($this->headers)
            ->postJson('/api/daily-questions/answer', [
                'question_id' => $question->id,
                'selected_option_index' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('answer_status', 'evaluated')
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('points', 5)
            ->assertJsonPath('total_points', 5)
            ->assertJsonPath('hip_score', 5);

        $this->assertDatabaseHas('user_answers', [
            'user_id' => $this->user->id,
            'question_id' => $question->id,
            'selected_option_index' => 1,
            'score' => 5,
            'status' => 'evaluated',
            'is_correct' => true,
        ]);
        $this->assertDatabaseHas('answers', [
            'user_id' => $this->user->id,
            'question_id' => $question->id,
            'answer_status' => 'correct',
            'score' => 5,
        ]);

        // The answer is final once scored.
        $this->withHeaders($this->headers)
            ->postJson('/api/daily-questions/answer', [
                'question_id' => $question->id,
                'selected_option_index' => 0,
            ])
            ->assertStatus(409);

        $this->withHeaders($this->headers)
            ->getJson('/api/daily-question/today')
            ->assertOk()
            ->assertJsonPath('has_answered', true)
            ->assertJsonPath('answer_status', 'evaluated')
            ->assertJsonPath('can_update', false)
            ->assertJsonPath('user_score_today', 5)
            ->assertJsonPath('is_correct', true);

        // The midnight command must not award the same answer twice.
        $this->travelTo(now()->addDay()->setTime(0, 0, 5));
        $this->artisan('app:evaluate-daily-questions')->assertSuccessful();
        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'total_points' => 5, 'hip_score' => 5]);

        $this->withHeaders($this->headers)
            ->getJson('/api/daily-questions/history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.selected_option', 'Second option')
            ->assertJsonPath('data.0.status', 'evaluated')
            ->assertJsonPath('data.0.is_correct', true)
            ->assertJsonPath('data.0.points', 5);
    }

    #[Test]
    public function a_partial_credit_answer_is_marked_incorrect_but_keeps_its_points(): void
    {
        $question = $this->question(today());

        $this->withHeaders($this->headers)
            ->postJson('/api/daily-questions/answer', [
                'question_id' => $question->id,
                'selected_option_index' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('answer_status', 'evaluated')
            ->assertJsonPath('is_correct', false)
            ->assertJsonPath('points', 2)
            ->assertJsonPath('hip_score', 2);
    }

    #[Test]
    public function answers_left_pending_by_the_old_flow_are_scored_when_history_loads(): void
    {
        $question = $this->question(today()->subDay());
        UserAnswer::create([
            'user_id' => $this->user->id,
            'question_id' => $question->id,
            'selected_option_index' => 1,
            'answer_date' => today()->subDay(),
            'status' => UserAnswer::STATUS_PENDING,
        ]);

        $this->withHeaders($this->headers)
            ->getJson('/api/daily-questions/history')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'evaluated')
            ->assertJsonPath('data.0.is_correct', true)
            ->assertJsonPath('data.0.points', 5);

        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'total_points' => 5, 'hip_score' => 5]);

        // Loading again does not award the points a second time.
        $this->withHeaders($this->headers)->getJson('/api/daily-questions/history')->assertOk();
        $this->assertSame(5.0, (float) $this->user->fresh()->total_points);
    }

    private function question($date): Question
    {
        return Question::create([
            'profession_id' => $this->profession->id,
            'category' => 'Core Intelligence',
            'question' => 'Choose the option with its server-defined score.',
            'options' => [
                ['text' => 'First option', 'score' => 2],
                ['text' => 'Second option', 'score' => 5],
            ],
            'scheduled_date' => $date,
            'is_used' => true,
        ]);
    }
}
