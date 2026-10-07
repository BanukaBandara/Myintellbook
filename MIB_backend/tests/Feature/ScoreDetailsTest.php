<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\ExamSession;
use App\Models\profession;
use App\Models\ProfessionalVerification;
use App\Models\Question;
use App\Models\TribunalReport;
use App\Models\User;
use App\Models\UserAnswer;
use App\Services\HipScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScoreDetailsTest extends TestCase
{
    use RefreshDatabase;

    private const SECTION_BUCKETS = [
        'identity' => 'pcm',
        'education' => 'education',
        'experience' => 'experience',
        'formal_recognition' => 'formal_recognition',
        'daily_questions' => 'tqm',
        'exams' => 'em',
        'others' => 'others_legal',
    ];

    #[Test]
    public function each_tab_lists_its_records_and_totals_match_the_lci_breakdown(): void
    {
        $user = User::create([
            'name' => 'Score Details Test',
            'email' => uniqid('score-details-', true).'@example.com',
            'password' => 'password123',
        ]);
        $this->actingAs($user);

        $user->educations()->create(['school' => 'Uni', 'degree' => 'Information Technology', 'field_of_study' => 'IT', 'category' => 'Bachelors']);
        $user->educations()->create(['school' => 'Uni', 'degree' => 'Artificial Intelligence', 'field_of_study' => 'AI', 'category' => 'Masters']);
        $user->workExperiances()->create([
            'title' => 'Engineer', 'company' => 'Soiltech', 'currently_working' => false, 'location' => 'Remote',
            'selectEmpType' => 1, 'locationType' => 1, 'starting_date' => '2020-01-01', 'end_date' => '2023-01-01',
            'positionType' => 'executive',
        ]);

        // The last two share the 'patent' scoring key, so only the first is credited.
        foreach ([['profile verification', 'profile verification'], ['Patent', 'Inventions'], ['Patent', 'Engineering award']] as [$title, $category]) {
            Achievement::create(['user_id' => $user->id, 'title' => $title, 'category' => $category, 'verification_status' => 'verified']);
        }
        ProfessionalVerification::create([
            'user_id' => $user->id, 'profession_type' => 'attorney_at_law', 'verification_status' => 'verified', 'verified_at' => now(),
        ]);
        TribunalReport::create(['user_id' => $user->id, 'violation_type' => 'legal conviction', 'status' => 'confirmed']);

        $profession = profession::create(['category_id' => Category::create(['name' => 'Score test'])->id, 'name' => 'Score test']);
        foreach ([[10, UserAnswer::STATUS_EVALUATED], [7, UserAnswer::STATUS_PENDING]] as [$score, $status]) {
            $question = Question::create([
                'profession_id' => $profession->id, 'category' => 'Testing', 'question' => "Question worth {$score}",
                'options' => [['text' => 'Option', 'score' => $score]], 'is_used' => false,
            ]);
            UserAnswer::create([
                'user_id' => $user->id, 'question_id' => $question->id, 'selected_option_index' => 0,
                'score' => $score, 'status' => $status, 'is_correct' => true,
            ]);
        }

        foreach ([[ExamSession::STATUS_COMPLETED, 81], [ExamSession::STATUS_EXPIRED, 0], [ExamSession::STATUS_IN_PROGRESS, null]] as [$status, $score]) {
            ExamSession::create([
                'user_id' => $user->id, 'category_id' => 1, 'token' => (string) Str::uuid(), 'question_ids' => range(1, 30),
                'status' => $status, 'started_at' => now(), 'expires_at' => now()->addMinutes(15),
                'correct_count' => $score === null ? null : 27, 'score' => $score,
            ]);
        }

        $token = Str::random(40);
        ApiToken::create(['user_id' => $user->id, 'token' => hash('sha256', $token), 'expires_at' => now()->addDay()]);

        $data = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/get-scores')
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.education.1.title', 'Information Technology')
            ->assertJsonPath('data.education.1.subtitle', 'Bachelors')
            ->assertJsonPath('data.experience.0.title', 'Soiltech')
            ->assertJsonPath('data.experience.0.subtitle', 'executive')
            ->assertJsonPath('data.formal_recognition.1.status', 'already credited')
            ->assertJsonPath('data.formal_recognition.1.points', 0)
            ->assertJsonCount(2, 'data.exams')
            ->assertJsonPath('data.exams.0.title', 'Core Intelligence & Self Awareness')
            ->json('data');

        $breakdown = HipScoreCalculator::breakdown($user->fresh());
        foreach (self::SECTION_BUCKETS as $section => $bucket) {
            $this->assertEqualsWithDelta(
                $breakdown[$bucket],
                collect($data[$section])->sum('points'),
                0.01,
                "The {$section} tab total does not match the {$bucket} LCI bucket.",
            );
        }
        $this->assertEqualsWithDelta(array_sum($breakdown), $data['totalScore'], 0.01);
    }

    #[Test]
    public function a_user_without_records_gets_empty_tabs(): void
    {
        $user = User::create([
            'name' => 'Empty Score Test',
            'email' => uniqid('score-empty-', true).'@example.com',
            'password' => 'password123',
        ]);
        $token = Str::random(40);
        ApiToken::create(['user_id' => $user->id, 'token' => hash('sha256', $token), 'expires_at' => now()->addDay()]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/get-scores')
            ->assertOk()
            ->assertExactJson(['code' => 200, 'data' => [
                ...array_fill_keys(array_keys(self::SECTION_BUCKETS), []),
                'totalScore' => 0,
            ]]);
    }
}
