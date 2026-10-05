<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Category;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\User;
use App\Models\UserLearnEnrollment;
use App\Models\profession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExamSessionTest extends TestCase
{
    use RefreshDatabase;

    private array $headers = [];
    private User $user;
    private profession $profession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(10, 0));

        $this->user = User::create([
            'email' => Str::lower(Str::random(12)).'@example.com',
            'password' => 'password123',
        ]);
        $rawToken = Str::random(40);
        ApiToken::create([
            'user_id' => $this->user->id,
            'token' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(30),
        ]);
        $this->headers = ['Authorization' => "Bearer {$rawToken}", 'Accept' => 'application/json'];

        $category = Category::create(['name' => 'Exam Test']);
        $this->profession = profession::create(['category_id' => $category->id, 'name' => 'Exam Test']);
    }

    #[Test]
    public function an_exam_has_30_masked_questions_and_is_scored_on_submission(): void
    {
        $this->questions('Leadership', 40);

        $start = $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => 4])
            ->assertCreated()
            ->assertJsonCount(30, 'questions')
            ->assertJsonPath('questions.0.options.1', ['id' => 1, 'text' => 'Best'])
            ->assertJsonMissingPath('questions.0.options.0.points')
            ->assertJsonMissingPath('questions.0.options.0.is_correct');
        $this->assertSame(900, $start->json('remaining_seconds'));

        $questions = $start->json('questions');
        $answers = [];
        foreach ($questions as $i => $question) {
            // 20 best answers (5 pts), 5 partial (2 pts), 5 skipped.
            $answers[] = ['question_id' => $question['id'], 'answer_id' => $i < 20 ? 1 : ($i < 25 ? 2 : null)];
        }
        // Answers for questions outside the session are ignored.
        $answers[] = ['question_id' => 999999, 'answer_id' => 1];

        $payload = [
            'exam_session_id' => $start->json('exam_session_id'),
            'session_token' => $start->json('session_token'),
            'answers' => array_slice($answers, 0, 30),
        ];

        $this->travel(10)->minutes();

        $this->withHeaders($this->headers)->postJson('/api/exam/submit', $payload)
            ->assertOk()
            ->assertJsonPath('result.correct_count', 20)
            ->assertJsonPath('result.answered_count', 25)
            ->assertJsonPath('result.total_score', 110)
            ->assertJsonPath('result.max_score', 150)
            ->assertJsonPath('result.points_gained', 110);

        $this->assertSame(110.0, (float) $this->user->fresh()->total_points);
        $this->assertSame(110.0, (float) $this->user->fresh()->hip_score);

        // A second submit (auto-submit racing a click) returns the same result without re-awarding.
        $this->withHeaders($this->headers)->postJson('/api/exam/submit', $payload)
            ->assertOk()
            ->assertJsonPath('already_submitted', true)
            ->assertJsonPath('result.total_score', 110);
        $this->assertSame(110.0, (float) $this->user->fresh()->total_points);
    }

    #[Test]
    public function submissions_after_the_15_minute_window_are_rejected(): void
    {
        $this->questions('QMS', 30);
        $start = $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => 6])->assertCreated();

        $this->travel(15)->minutes();
        $this->travel(ExamSession::GRACE_SECONDS + 1)->seconds();

        $this->withHeaders($this->headers)->postJson('/api/exam/submit', [
            'exam_session_id' => $start->json('exam_session_id'),
            'session_token' => $start->json('session_token'),
            'answers' => [['question_id' => $start->json('questions.0.id'), 'answer_id' => 1]],
        ])->assertStatus(422);

        $this->assertDatabaseHas('exam_sessions', ['id' => $start->json('exam_session_id'), 'status' => 'expired']);
        $this->assertSame(0.0, (float) $this->user->fresh()->total_points);
    }

    #[Test]
    public function the_auto_submit_at_zero_is_accepted_within_the_grace_window(): void
    {
        $this->questions('QMS', 30);
        $start = $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => 6])->assertCreated();

        $this->travel(15)->minutes();
        $this->travel(5)->seconds();

        $this->withHeaders($this->headers)->postJson('/api/exam/submit', [
            'exam_session_id' => $start->json('exam_session_id'),
            'session_token' => $start->json('session_token'),
            'answers' => [],
        ])->assertOk()->assertJsonPath('result.total_score', 0);
    }

    #[Test]
    public function one_attempt_per_category_per_day_and_reloads_resume_the_running_exam(): void
    {
        $this->questions('Leadership', 30);
        $this->questions('QMS', 30);

        $first = $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => 4])->assertCreated();

        // Starting again while running resumes the same session, even for another category.
        $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => 6])
            ->assertOk()
            ->assertJsonPath('resumed', true)
            ->assertJsonPath('exam_session_id', $first->json('exam_session_id'));

        $this->withHeaders($this->headers)->getJson('/api/exam/categories')
            ->assertOk()
            ->assertJsonPath('in_progress.exam_session_id', $first->json('exam_session_id'));

        $this->withHeaders($this->headers)->postJson('/api/exam/submit', [
            'exam_session_id' => $first->json('exam_session_id'),
            'session_token' => $first->json('session_token'),
            'answers' => [],
        ])->assertOk();

        $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => 4])->assertStatus(409);
        $this->withHeaders($this->headers)->getJson('/api/exam/categories')
            ->assertJsonPath('categories.3.attempted_today', true)
            ->assertJsonPath('categories.5.attempted_today', false)
            ->assertJsonPath('in_progress', null);

        $this->travel(1)->days();
        $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => 4])->assertCreated();
    }

    #[Test]
    public function questions_studied_in_learn_are_avoided_when_enough_others_exist(): void
    {
        $studied = $this->questions('Leadership', 30)->pluck('id')->all();
        $fresh = $this->questions('Leadership', 30)->pluck('id')->all();
        UserLearnEnrollment::create([
            'user_id' => $this->user->id,
            'category_id' => 4,
            'question_ids' => $studied,
            'status' => 'expired',
            'enrolled_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $ids = $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => 4])
            ->assertCreated()
            ->json('questions.*.id');

        $this->assertEqualsCanonicalizing($fresh, $ids);
    }

    #[Test]
    public function questions_are_not_repeated_while_at_least_30_unseen_remain(): void
    {
        $this->questions('Leadership', 60);

        $day1 = $this->startAndSubmit(4);
        $this->travel(1)->days();
        $start = $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => 4])
            ->assertCreated()
            ->assertJsonPath('pool_reset', false)
            ->assertJsonMissingPath('message');

        $this->assertEmpty(array_intersect($day1, $start->json('questions.*.id')));
        $this->assertDatabaseCount('exam_pool_resets', 0);
    }

    #[Test]
    public function the_pool_resets_when_fewer_than_30_unseen_questions_remain(): void
    {
        $this->questions('Leadership', 45);

        $day1 = $this->startAndSubmit(4);
        $this->travel(1)->days();

        $start = $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => 4])
            ->assertCreated()
            ->assertJsonPath('pool_reset', true)
            ->assertJsonPath('message', 'Congratulations! You have completed all 45 questions in this category. '
                .'The question pool has been reset for continuous practice.')
            ->assertJsonCount(30, 'questions');
        $day2 = $start->json('questions.*.id');
        $this->submitEmpty($start);

        // The 15 leftover unseen questions are used first, the rest refilled from the reset pool.
        $leftover = array_diff(Question::query()->pluck('id')->all(), $day1);
        $this->assertEmpty(array_diff($leftover, $day2));
        $this->assertDatabaseHas('exam_pool_resets', ['user_id' => $this->user->id, 'category_id' => 4, 'pool_size' => 45]);

        // Exam history is kept (scores feed HIP); only the "seen" cycle restarted.
        $this->assertDatabaseCount('exam_sessions', 2);

        // New cycle: day 3 avoids day 2's questions (15 unseen left → resets again).
        $this->travel(1)->days();
        $day3 = $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => 4])
            ->assertCreated()
            ->assertJsonPath('pool_reset', true)
            ->json('questions.*.id');
        $notInDay2 = array_diff(Question::query()->pluck('id')->all(), $day2);
        $this->assertEmpty(array_diff($notInDay2, $day3));
    }

    #[Test]
    public function the_result_includes_a_breakdown_with_correct_answers_and_the_updated_hip_score(): void
    {
        $this->questions('QMS', 30);
        $start = $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => 6])->assertCreated();
        $first = $start->json('questions.0.id');

        $response = $this->withHeaders($this->headers)->postJson('/api/exam/submit', [
            'exam_session_id' => $start->json('exam_session_id'),
            'session_token' => $start->json('session_token'),
            'answers' => [['question_id' => $first, 'answer_id' => 2]],
        ])->assertOk()
            ->assertJsonPath('result.hip_score', 2)
            ->assertJsonPath('result.total_points', 2)
            ->assertJsonCount(30, 'result.breakdown')
            ->assertJsonPath('result.breakdown.0.question_id', $first)
            ->assertJsonPath('result.breakdown.0.selected_answer', ['id' => 2, 'text' => 'Partial'])
            ->assertJsonPath('result.breakdown.0.correct_answers', ['Best'])
            ->assertJsonPath('result.breakdown.0.points_earned', 2)
            ->assertJsonPath('result.breakdown.0.max_points', 5)
            ->assertJsonPath('result.breakdown.0.is_correct', false)
            ->assertJsonPath('result.breakdown.1.selected_answer', null);
    }

    #[Test]
    public function another_users_session_or_a_wrong_token_cannot_be_submitted(): void
    {
        $this->questions('QMS', 30);
        $start = $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => 6])->assertCreated();

        $this->withHeaders($this->headers)->postJson('/api/exam/submit', [
            'exam_session_id' => $start->json('exam_session_id'),
            'session_token' => (string) Str::uuid(),
            'answers' => [],
        ])->assertNotFound();
    }

    private function startAndSubmit(int $categoryId): array
    {
        $start = $this->withHeaders($this->headers)->postJson('/api/exam/start', ['category_id' => $categoryId])->assertCreated();
        $this->submitEmpty($start);

        return $start->json('questions.*.id');
    }

    private function submitEmpty($start): void
    {
        $this->withHeaders($this->headers)->postJson('/api/exam/submit', [
            'exam_session_id' => $start->json('exam_session_id'),
            'session_token' => $start->json('session_token'),
            'answers' => [],
        ])->assertOk();
    }

    private function questions(string $category, int $count)
    {
        return collect(range(1, $count))->map(fn (int $n) => Question::create([
            'profession_id' => $this->profession->id,
            'category' => $category,
            'question' => "{$category} question {$n}",
            'options' => [
                ['text' => 'Worst', 'score' => 0],
                ['text' => 'Best', 'score' => 5],
                ['text' => 'Partial', 'score' => 2],
            ],
            'is_used' => true,
        ]));
    }
}
