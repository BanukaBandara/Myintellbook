<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileProfessionFallbackTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function profile_details_use_latest_saved_experience_when_none_is_current(): void
    {
        $user = User::create([
            'email' => Str::lower(Str::random(12)).'@example.com',
            'password' => 'password123',
        ]);

        DB::table('work_experiances')->insert([
            'user_id' => $user->id,
            'title' => 'Software Engineer',
            'company' => 'UP Engineering',
            'currently_working' => false,
            'location' => 'Panadura',
            'selectEmpType' => 1,
            'locationType' => 2,
            'starting_date' => now()->subYears(2)->toDateString(),
            'end_date' => now()->subYear()->toDateString(),
            'positionType' => 'executive',
            'created_at' => now()->subYear(),
            'updated_at' => now()->subYear(),
        ]);

        $this->assertSame([
            'company' => 'UP Engineering',
            'location' => 'Panadura',
            'profession' => 'Software Engineer',
        ], getProfession($user->id));
    }
}
