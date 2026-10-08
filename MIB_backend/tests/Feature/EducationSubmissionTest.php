<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EducationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function education_submission_succeeds_when_scoring_configuration_is_missing(): void
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
            ->postJson('/api/add-education-details', [
                'school' => 'Test University',
                'degree' => 'Bachelor of Science',
                'field_of_study' => 'Computer Science',
                'degree_category' => 'Bachelors',
            ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('education', [
            'user_id' => $user->id,
            'school' => 'Test University',
            'category' => 'Bachelors',
        ]);
        $this->assertSame(12275.0, (float) $user->fresh()->hip_score);

        $educationId = \App\Models\Education::query()
            ->where('user_id', $user->id)
            ->value('id');

        $this->postJson('/api/edit-education-detail', [
            'id' => $educationId,
            'school' => 'Test University',
            'degree' => 'Master of Science',
            'field_of_study' => 'Computer Science',
            'degree_category' => 'Masters',
        ])->assertOk();
        $this->assertSame(18412.5, (float) $user->fresh()->hip_score);

        $this->getJson("/api/delete-education/{$educationId}")->assertOk();
        $this->assertSame(0.0, (float) $user->fresh()->hip_score);
    }
}
