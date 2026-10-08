<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\profession;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\Question;
use App\Models\TribunalReport;
use App\Models\User;
use App\Models\UserAnswer;
use App\Services\HipScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HipScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sums_each_scoring_engine_and_recalculates_on_component_changes(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        foreach ([
            'PhD',
            'Masters',
            'Bachelors',
            'Diploma 3Y+',
            'Certificate',
        ] as $category) {
            $user->educations()->create([
                'school' => 'Test institution',
                'degree' => $category,
                'field_of_study' => 'Testing',
                'category' => $category,
            ]);
        }

        foreach ([
            ['executive', '2020-01-01', '2021-01-01'],
            ['non-executive', '2020-01-01', '2022-01-01'],
            ['life care', '2020-01-01', '2023-01-01'],
            ['general life', '2020-01-01', '2024-01-01'],
        ] as [$position, $start, $end]) {
            $user->workExperiances()->create([
                'title' => 'Test role',
                'company' => 'Test company',
                'currently_working' => false,
                'location' => 'Remote',
                'selectEmpType' => 1,
                'locationType' => 1,
                'starting_date' => $start,
                'end_date' => $end,
                'positionType' => $position,
            ]);
        }

        foreach ([
            'founder',
            'author',
            'patent',
            'tech startup',
            'iso certified',
            'profile verification',
        ] as $category) {
            Achievement::create([
                'user_id' => $user->id,
                'title' => $category,
                'category' => $category,
                'verification_status' => 'verified',
            ]);
        }

        ProfessionalVerification::create([
            'user_id' => $user->id,
            'profession_type' => 'attorney_at_law',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        foreach ([
            'ethical/bribery breach',
            'extremism',
            'legal conviction',
            'employment disciplinary action',
        ] as $violationType) {
            TribunalReport::create([
                'user_id' => $user->id,
                'violation_type' => $violationType,
                'status' => 'confirmed',
            ]);
        }

        $this->assertSame(134207.0, (float) $user->fresh()->hip_score);
    }

    #[Test]
    public function it_scores_only_verified_achievements_and_confirmed_penalties(): void
    {
        $user = $this->createUser();

        $achievement = Achievement::create([
            'user_id' => $user->id,
            'title' => 'Founder',
            'category' => 'founder',
            'verification_status' => 'pending',
        ]);
        $report = TribunalReport::create([
            'user_id' => $user->id,
            'violation_type' => 'legal conviction',
            'status' => 'pending',
        ]);

        $this->assertSame(0.0, (float) $user->fresh()->hip_score);

        $achievement->update(['verification_status' => 'verified']);
        $this->assertSame(36825.0, (float) $user->fresh()->hip_score);

        $report->update(['status' => 'confirmed']);
        $this->assertSame(18412.5, (float) $user->fresh()->hip_score);

        $report->delete();
        $this->assertSame(36825.0, (float) $user->fresh()->hip_score);

        $achievement->delete();
        $this->assertSame(0.0, (float) $user->fresh()->hip_score);
    }

    #[Test]
    public function it_recalculates_when_education_or_experience_is_updated_or_deleted(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $education = $user->educations()->create([
            'school' => 'Test institution',
            'degree' => 'Bachelor',
            'field_of_study' => 'Testing',
            'category' => 'Bachelors',
        ]);
        $this->assertSame(12275.0, (float) $user->fresh()->hip_score);

        $education->update(['category' => 'Masters']);
        $this->assertSame(18412.5, (float) $user->fresh()->hip_score);

        $experience = $user->workExperiances()->create([
            'title' => 'Test role',
            'company' => 'Test company',
            'currently_working' => false,
            'location' => 'Remote',
            'selectEmpType' => 1,
            'locationType' => 1,
            'starting_date' => '2020-01-01',
            'end_date' => '2022-01-01',
            'positionType' => 'executive',
        ]);
        $this->assertSame(23322.5, (float) $user->fresh()->hip_score);

        $experience->update(['positionType' => 'life care']);
        $this->assertSame(21685.5, (float) $user->fresh()->hip_score);

        $education->delete();
        $this->assertSame(3273.0, (float) $user->fresh()->hip_score);

        $experience->delete();
        $this->assertSame(0.0, (float) $user->fresh()->hip_score);
    }

    #[Test]
    public function the_recalculation_command_includes_education_experience_and_daily_answers(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $user->educations()->create([
            'school' => 'Test institution',
            'degree' => 'Bachelor',
            'field_of_study' => 'Testing',
            'category' => 'Bachelors',
        ]);
        $user->workExperiances()->create([
            'title' => 'Test role',
            'company' => 'Test company',
            'currently_working' => false,
            'location' => 'Remote',
            'selectEmpType' => 1,
            'locationType' => 1,
            'starting_date' => '2020-01-01',
            'end_date' => '2022-01-01',
            'positionType' => 'executive',
        ]);
        $category = Category::create(['name' => 'HIP score test']);
        $profession = profession::create([
            'category_id' => $category->id,
            'name' => 'HIP score test',
        ]);
        $question = Question::create([
            'profession_id' => $profession->id,
            'category' => 'Testing',
            'question' => 'Test daily answer score aggregation',
            'options' => [['text' => 'Test option', 'score' => 4.25]],
            'is_used' => false,
        ]);
        UserAnswer::create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'selected_option_index' => 0,
            'score' => 4.25,
            'status' => UserAnswer::STATUS_EVALUATED,
        ]);

        $user->update(['hip_score' => 5]);

        Artisan::call('hip:recalculate-all');

        $this->assertSame(17189.25, (float) $user->fresh()->hip_score);
        $this->assertStringContainsString('Recalculated HIP scores for 1 users.', Artisan::output());
    }

    #[Test]
    public function it_skips_optional_achievement_and_penalty_tables_when_they_are_missing(): void
    {
        $user = $this->createUser();
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('tribunal_reports');

        $this->assertSame(0.0, HipScoreCalculator::recalculate($user));
    }

    #[Test]
    public function achievements_have_the_requested_columns_and_default_to_verified(): void
    {
        $user = $this->createUser();

        $this->assertTrue(Schema::hasColumn('achievements', 'title'));
        $this->assertTrue(Schema::hasColumn('achievements', 'category'));

        $achievement = $user->achievements()->create([
            'title' => 'Published book',
            'category' => 'Author / Published Book',
        ]);

        $this->assertSame('verified', $achievement->fresh()->verification_status);
        $this->assertSame(24550.0, (float) $user->fresh()->hip_score);
    }

    #[Test]
    public function dashboard_profile_returns_the_recalculated_hip_score(): void
    {
        $user = $this->createUser();
        $category = Category::create(['name' => 'Dashboard test']);
        $profession = profession::create([
            'category_id' => $category->id,
            'name' => 'Dashboard test',
        ]);
        Profile::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'gender' => 1,
            'profession_id' => $profession->id,
            'birth_date' => '1990-01-01',
        ]);
        $user->educations()->create([
            'school' => 'Test University',
            'degree' => 'Bachelor of Science (BSc)',
            'field_of_study' => 'Testing',
            'category' => 'Degree',
        ]);
        $token = Str::random(40);
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/get-user-summary')
            ->assertOk()
            ->assertJsonPath('data.0.hip_score', 12275)
            ->assertJsonPath('data.0.Hip', 12275);

        $this->assertSame(12275.0, (float) $user->fresh()->hip_score);
    }

    #[Test]
    public function authenticated_user_profile_api_includes_hip_score_in_the_user_object(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $user->educations()->create([
            'school' => 'Test University',
            'degree' => 'Bachelor of Science',
            'field_of_study' => 'Testing',
            'category' => 'Bachelors',
        ]);
        $token = Str::random(40);
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user/profile')
            ->assertOk()
            ->assertJsonPath('user.hip_score', 12275)
            ->assertJsonPath('hip_score', 12275);
    }

    #[Test]
    public function profile_widget_can_load_another_users_public_profile_by_id(): void
    {
        $viewer = $this->createUser();
        $this->actingAs($viewer);
        $profileUser = $this->createUser();
        $category = Category::create(['name' => 'Public profile test']);
        $profession = profession::create([
            'category_id' => $category->id,
            'name' => 'Public profile test',
        ]);
        Profile::create([
            'user_id' => $profileUser->id,
            'first_name' => 'Public',
            'last_name' => 'Profile',
            'gender' => 1,
            'profession_id' => $profession->id,
            'birth_date' => '1990-01-01',
        ]);
        $profileUser->educations()->create([
            'school' => 'Public University',
            'degree' => 'Bachelor of Science',
            'field_of_study' => 'Testing',
            'category' => 'Bachelors',
        ]);
        $profileUser->workExperiances()->create([
            'title' => 'Engineer',
            'company' => 'Example Company',
            'currently_working' => false,
            'location' => 'Remote',
            'selectEmpType' => 1,
            'locationType' => 1,
            'starting_date' => '2020-01-01',
            'end_date' => '2022-01-01',
            'positionType' => 'executive',
        ]);
        Achievement::create([
            'user_id' => $profileUser->id,
            'title' => 'Published book',
            'category' => 'Author',
            'verification_status' => 'verified',
        ]);
        $token = Str::random(40);
        ApiToken::create([
            'user_id' => $viewer->id,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/users/{$profileUser->id}")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user.id', $profileUser->id)
            ->assertJsonPath('user.first_name', 'Public')
            ->assertJsonPath('user.hip_score', 41735)
            ->assertJsonPath('user.education.0.school', 'Public University')
            ->assertJsonPath('user.experiance.0.title', 'Engineer')
            ->assertJsonPath('user.achievements.0.category', 'Author')
            ->assertJsonMissingPath('user.email')
            ->assertJsonMissingPath('user.phone')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.tribunal_private_notes');
    }

    #[Test]
    public function missing_public_profile_returns_a_json_not_found_response(): void
    {
        $viewer = $this->createUser();
        $token = Str::random(40);
        ApiToken::create([
            'user_id' => $viewer->id,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users/99999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'User profile not found']);
    }

    #[Test]
    public function it_maps_bachelor_label_variants_from_category_or_degree(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        foreach ([
            ["Bachelor's", 'Unclassified degree'],
            ['Bachelors', 'Unclassified degree'],
            ['Degree', 'BSc'],
            ['Unclassified', 'Bachelor of Science'],
        ] as [$category, $degree]) {
            $user->educations()->create([
                'school' => 'Test institution',
                'degree' => $degree,
                'field_of_study' => 'Testing',
                'category' => $category,
            ]);
        }

        $this->assertSame(49100.0, HipScoreCalculator::recalculate($user));
    }

    private function createUser(): User
    {
        return User::create([
            'name' => 'HIP Score Test',
            'email' => uniqid('hip-score-', true).'@example.com',
            'password' => 'password123',
        ]);
    }
}
