<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\WorkExperiance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkExperienceSubmissionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function work_experience_submission_succeeds_when_scoring_configuration_is_missing(): void
    {
        Notification::fake();

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

        $response = $this->withHeader('Authorization', "Bearer {$rawToken}")
            ->postJson('/api/add-work-experiance', [
                'title' => 'Software Engineer',
                'company' => 'Example Company',
                'currently_working' => 1,
                'location' => 'Remote',
                'selectEmpType' => 1,
                'locationType' => 2,
                'startingDate' => '2020-01-01',
                'endDate' => now()->toDateString(),
                'position' => 'executive',
            ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('work_experiances', [
            'user_id' => $user->id,
            'title' => 'Software Engineer',
            'company' => 'Example Company',
        ]);
        $this->assertSame(
            round(2455.0 * Carbon::parse('2020-01-01')->diffInYears(Carbon::today()), 2),
            (float) $user->fresh()->hip_score,
        );

        $experienceId = WorkExperiance::query()
            ->where('user_id', $user->id)
            ->value('id');

        $this->postJson('/api/edit-details-experiance', [
            'id' => $experienceId,
            'title' => 'Care Specialist',
            'company' => 'Example Company',
            'currently_working' => 0,
            'location' => 'Remote',
            'selectEmpType' => 1,
            'locationType' => 2,
            'startingDate' => '2020-01-01',
            'endDate' => '2022-01-01',
            'position' => 'life care',
        ])->assertOk();
        $this->assertSame(3273.0, (float) $user->fresh()->hip_score);

        $this->getJson("/api/delete-experiance/{$experienceId}")->assertOk();
        $this->assertSame(0.0, (float) $user->fresh()->hip_score);
    }
}
