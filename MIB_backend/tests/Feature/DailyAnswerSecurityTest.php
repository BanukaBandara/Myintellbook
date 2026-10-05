<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Category;
use App\Models\Question;
use App\Models\User;
use App\Models\profession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DailyAnswerSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function answers_can_be_changed_until_midnight_and_are_scored_by_the_evaluation_command(): void
    {
        Notification::fake();
        $this->travelTo(now()->setTime(10, 0));

        $user = User::create([
            'email' => Str::lower(Str::random(12)).'@example.com',
            'password' => 'password123',
        ]);
        $rawToken = Str::random(40);
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(3),
        ]);

        $category = Category::create(['name' => 'Daily Questions Test']);
        $profession = profession::create([
            'category_id' => $category->id,
            'name' => 'Daily Questions Test',
        ]);
        $question = Question::create([
            'profession_id' => $profession->id,
            'category' => 'Core Intelligence',
            'question' => 'Choose the option with its server-defined score.',
            'options' => [
                ['text' => 'First option', 'score' => 2],
                ['text' => 'Second option', 'score' => 5],
            ],
            'scheduled_date' => today(),
            'is_used' => true,
        ]);

        $headers = [
            'Authorization' => "Bearer {$rawToken}",
            'Accept' => 'application/json',
        ];

        $this->withHeaders($headers)
            ->postJson('/api/daily-questions/answer', [
                'question_id' => $question->id,
                'selected_option_index' => 0,
                'score' => 5,
            ])
            ->assertUnprocessable();

        $this->withHeaders($headers)
            ->postJson('/api/daily-questions/answer', [
                'question_id' => $question->id,
                'selected_option_index' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('answer_status', 'pending_evaluation')
            ->assertJsonPath('updated', false);

        $this->withHeaders($headers)
            ->postJson('/api/daily-questions/answer', [
                'question_id' => $question->id,
                'selected_option_index' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('updated', true);

        // No points are awarded during the day.
        $this->assertDatabaseCount('user_answers', 1);
        $this->assertDatabaseHas('user_answers', [
            'user_id' => $user->id,
            'question_id' => $question->id,
            'selected_option_index' => 1,
            'score' => null,
            'status' => 'pending_evaluation',
        ]);
        $this->assertDatabaseMissing('answers', ['user_id' => $user->id]);
        $this->assertSame(0.0, (float) $user->fresh()->total_points);

        $this->withHeaders($headers)
            ->getJson('/api/daily-question/today')
            ->assertOk()
            ->assertJsonPath('has_answered', true)
            ->assertJsonPath('answer_status', 'pending_evaluation')
            ->assertJsonPath('selected_option_index', 1)
            ->assertJsonPath('can_update', true)
            ->assertJsonPath('user_score_today', null);

        // After midnight the question is closed and the command scores it.
        $this->travelTo(now()->addDay()->setTime(0, 0, 5));

        $this->withHeaders($headers)
            ->postJson('/api/daily-questions/answer', [
                'question_id' => $question->id,
                'selected_option_index' => 0,
            ])
            ->assertStatus(409);

        $this->artisan('app:evaluate-daily-questions')->assertSuccessful();

        $this->assertDatabaseHas('user_answers', [
            'user_id' => $user->id,
            'question_id' => $question->id,
            'score' => 5,
            'status' => 'evaluated',
            'is_correct' => true,
        ]);
        $this->assertDatabaseHas('answers', [
            'user_id' => $user->id,
            'question_id' => $question->id,
            'answer_status' => 'correct',
            'score' => 5,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'total_points' => 5,
            'hip_score' => 5,
        ]);

        // Re-running must not award points twice.
        $this->artisan('app:evaluate-daily-questions')->assertSuccessful();
        $this->assertSame(5.0, (float) $user->fresh()->total_points);

        $this->withHeaders($headers)
            ->getJson('/api/daily-questions/history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.question_text', 'Choose the option with its server-defined score.')
            ->assertJsonPath('data.0.selected_option', 'Second option')
            ->assertJsonPath('data.0.is_correct', true)
            ->assertJsonPath('data.0.points', 5)
            ->assertJsonPath('data.0.status', 'evaluated');
    }
}
