<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Category;
use App\Models\Question;
use App\Models\User;
use App\Models\UserLearnEnrollment;
use App\Models\profession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LearnModuleTest extends TestCase
{
    use RefreshDatabase;

    private array $headers = [];
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    #[Test]
    public function enrolling_starts_a_seven_day_pass_with_up_to_100_questions_and_point_values(): void
    {
        $profession = $this->profession();
        foreach (range(1, 105) as $n) {
            $this->question($profession, 'Leadership', "Leadership question {$n}");
        }
        $this->question($profession, 'QMS', 'Other category question');

        $this->withHeaders($this->headers)->getJson('/api/learn/active')
            ->assertOk()
            ->assertJsonPath('active', false)
            ->assertJsonCount(6, 'categories')
            ->assertJsonPath('categories.3.name', 'Leadership & Decision-Making')
            ->assertJsonPath('categories.3.question_count', 100);

        $this->withHeaders($this->headers)->postJson('/api/learn/enroll', ['category_id' => 4])
            ->assertCreated()
            ->assertJsonPath('active', true)
            ->assertJsonPath('enrollment.category.id', 4)
            ->assertJsonCount(100, 'questions')
            ->assertJsonPath('questions.0.options.0.points', 0)
            ->assertJsonPath('questions.0.options.1.points', 5)
            ->assertJsonPath('questions.0.options.1.is_correct', true)
            ->assertJsonPath('questions.0.correct_answers', ['Best answer']);

        $enrollment = UserLearnEnrollment::query()->sole();
        $this->assertEqualsWithDelta(7 * 86400, $enrollment->enrolled_at->diffInSeconds($enrollment->expires_at), 1);

        // Only one active pass at a time.
        $this->withHeaders($this->headers)->postJson('/api/learn/enroll', ['category_id' => 6])
            ->assertStatus(409);

        // Same question set on every reload during the pass.
        $first = $this->withHeaders($this->headers)->getJson('/api/learn/active')
            ->assertOk()->assertJsonPath('active', true)->json('questions.*.id');
        $this->assertSame($enrollment->question_ids, $first);
    }

    #[Test]
    public function an_expired_pass_is_marked_expired_and_a_new_category_can_be_chosen(): void
    {
        $profession = $this->profession();
        $this->question($profession, 'Leadership', 'Leadership question');
        $this->question($profession, 'QMS', 'QMS question');

        $this->withHeaders($this->headers)->postJson('/api/learn/enroll', ['category_id' => 4])->assertCreated();

        $this->travel(7)->days();
        $this->travel(1)->second();

        $this->withHeaders($this->headers)->getJson('/api/learn/active')
            ->assertOk()
            ->assertJsonPath('active', false);
        $this->assertDatabaseHas('user_learn_enrollments', ['user_id' => $this->user->id, 'status' => 'expired']);

        $this->withHeaders($this->headers)->postJson('/api/learn/enroll', ['category_id' => 6])
            ->assertCreated()
            ->assertJsonPath('questions.0.question_text', 'QMS question');
    }

    #[Test]
    public function todays_daily_question_and_future_scheduled_questions_are_never_revealed(): void
    {
        $profession = $this->profession();
        $this->question($profession, 'Leadership', 'Today daily', ['scheduled_date' => today(), 'is_used' => true]);
        $this->question($profession, 'Leadership', 'Future daily', ['scheduled_date' => today()->addDays(3), 'is_used' => true]);
        $this->question($profession, 'Leadership', 'Past daily', ['scheduled_date' => today()->subDay(), 'is_used' => true]);

        $this->withHeaders($this->headers)->postJson('/api/learn/enroll', ['category_id' => 4])
            ->assertCreated()
            ->assertJsonCount(1, 'questions')
            ->assertJsonPath('questions.0.question_text', 'Past daily');
    }

    #[Test]
    public function finishing_a_learning_batch_marks_it_studied_awards_no_points_and_loads_unseen_questions(): void
    {
        $profession = $this->profession();
        foreach (range(1, 105) as $n) {
            $this->question($profession, 'Leadership', "Leadership question {$n}");
        }
        $this->user->forceFill(['total_points' => 23, 'hip_score' => 7])->save();

        $firstBatch = $this->withHeaders($this->headers)
            ->postJson('/api/learn/enroll', ['category_id' => 4])
            ->assertCreated()
            ->assertJsonPath('score', 0)
            ->assertJsonPath('points_awarded', 0)
            ->assertJsonPath('lci_points_awarded', 0)
            ->assertJsonPath('hip_points_awarded', 0);

        $firstEnrollmentId = $firstBatch->json('enrollment.id');
        $firstQuestionIds = $firstBatch->json('questions.*.id');
        $nextBatch = $this->withHeaders($this->headers)
            ->postJson('/api/learn/next', ['enrollment_id' => $firstEnrollmentId])
            ->assertOk()
            ->assertJsonPath('progress_status', 'Studied')
            ->assertJsonPath('score', 0)
            ->assertJsonPath('points_awarded', 0)
            ->assertJsonPath('lci_points_awarded', 0)
            ->assertJsonPath('hip_points_awarded', 0)
            ->assertJsonCount(5, 'questions');

        $nextEnrollmentId = $nextBatch->json('enrollment.id');
        $nextQuestionIds = $nextBatch->json('questions.*.id');
        $this->assertSame([], array_values(array_intersect($firstQuestionIds, $nextQuestionIds)));
        $this->assertDatabaseHas('user_learn_enrollments', [
            'id' => $firstEnrollmentId,
            'status' => UserLearnEnrollment::STATUS_STUDIED,
        ]);
        $this->assertDatabaseHas('user_learn_enrollments', [
            'id' => $nextEnrollmentId,
            'status' => UserLearnEnrollment::STATUS_ACTIVE,
        ]);

        // Retrying a completed batch cannot skip or mark the new batch as studied.
        $this->withHeaders($this->headers)
            ->postJson('/api/learn/next', ['enrollment_id' => $firstEnrollmentId])
            ->assertStatus(409)
            ->assertJsonPath('enrollment.id', $nextEnrollmentId);

        $this->withHeaders($this->headers)
            ->postJson('/api/learn/next', ['enrollment_id' => $nextEnrollmentId])
            ->assertOk()
            ->assertJsonPath('active', false)
            ->assertJsonPath('progress_status', 'Studied')
            ->assertJsonPath('score', 0)
            ->assertJsonPath('points_awarded', 0)
            ->assertJsonPath('lci_points_awarded', 0)
            ->assertJsonPath('hip_points_awarded', 0);

        $this->withHeaders($this->headers)->getJson('/api/learn/active')
            ->assertOk()
            ->assertJsonPath('active', false)
            ->assertJsonPath('categories.3.question_count', 0);

        $this->assertSame(23, $this->user->fresh()->total_points);
        $this->assertSame(7.0, (float) $this->user->fresh()->hip_score);
    }

    #[Test]
    public function invalid_category_is_rejected_and_auth_is_required(): void
    {
        $this->withHeaders($this->headers)->postJson('/api/learn/enroll', ['category_id' => 7])->assertUnprocessable();
        $this->flushHeaders()->getJson('/api/learn/active')->assertUnauthorized();
    }

    #[Test]
    public function topic_cards_are_listed_and_each_topic_request_returns_a_random_zero_point_batch(): void
    {
        $profession = $this->profession();
        foreach (range(1, 250) as $n) {
            $this->question($profession, 'Leadership', "Leadership question {$n}");
        }
        $this->user->forceFill(['total_points' => 23, 'hip_score' => 7])->save();

        $this->withHeaders($this->headers)->getJson('/api/learn/categories')
            ->assertOk()
            ->assertJsonPath('categories.3.question_count', 250);

        $firstBatch = $this->withHeaders($this->headers)->getJson('/api/learn/questions/4')
            ->assertOk()
            ->assertJsonPath('category.id', 4)
            ->assertJsonPath('pool_size', 250)
            ->assertJsonPath('batch_size', 100)
            ->assertJsonPath('score', 0)
            ->assertJsonPath('points_awarded', 0)
            ->assertJsonPath('lci_points_awarded', 0)
            ->assertJsonPath('hip_points_awarded', 0)
            ->assertJsonCount(100, 'questions');

        $secondBatch = $this->withHeaders($this->headers)->getJson('/api/learn/questions/4')
            ->assertOk()
            ->assertJsonCount(100, 'questions');

        $this->assertCount(100, array_unique($firstBatch->json('questions.*.id')));
        $this->assertCount(100, array_unique($secondBatch->json('questions.*.id')));
        $this->assertDatabaseCount('user_learn_enrollments', 0);
        $this->assertSame(23, $this->user->fresh()->total_points);
        $this->assertSame(7.0, (float) $this->user->fresh()->hip_score);

        $this->withHeaders($this->headers)->getJson('/api/learn/questions/7')->assertNotFound();
        $this->flushHeaders()->getJson('/api/learn/questions/4')->assertUnauthorized();
    }

    private function profession(): profession
    {
        $category = Category::create(['name' => 'Learn Test']);

        return profession::create(['category_id' => $category->id, 'name' => 'Learn Test']);
    }

    private function question(profession $profession, string $category, string $text, array $extra = []): Question
    {
        return Question::create([
            'profession_id' => $profession->id,
            'category' => $category,
            'question' => $text,
            'options' => [
                ['text' => 'Weak answer', 'score' => 0],
                ['text' => 'Best answer', 'score' => 5],
                ['text' => 'Partial answer', 'score' => 2],
            ],
            'is_used' => true,
            ...$extra,
        ]);
    }
}
