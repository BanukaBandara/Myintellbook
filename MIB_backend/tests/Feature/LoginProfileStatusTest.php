<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Profile;
use App\Models\User;
use App\Models\profession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginProfileStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_and_user_endpoints_report_profile_completion(): void
    {
        $user = User::create([
            'email' => Str::lower(Str::random(12)).'@example.com',
            'password' => 'password123',
        ]);

        $login = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('user.is_profile_completed', false)
            ->assertJsonPath('user.profile', null);

        $category = Category::create(['name' => 'Login Test']);
        $profession = profession::create(['category_id' => $category->id, 'name' => 'Login Test']);
        Profile::create([
            'user_id' => $user->id,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'gender' => 2,
            'birth_date' => '1990-01-01',
            'profession_id' => $profession->id,
        ]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('user.is_profile_completed', true)
            ->assertJsonPath('user.profile.first_name', 'Ada');

        $this->withHeaders(['Authorization' => 'Bearer '.$login->json('token'), 'Accept' => 'application/json'])
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.is_profile_completed', true)
            ->assertJsonPath('data.profile.last_name', 'Lovelace');
    }
}
