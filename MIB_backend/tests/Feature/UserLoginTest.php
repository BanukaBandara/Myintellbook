<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserLoginTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
    public function user_can_login_successfully(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'chamath.rmc@gmail.com',
            'password' => '7710578@Cha',
        ]);

        $response->assertStatus(200);
        // $this->assertDatabaseHas('users', ['token' => 'chamath.rmc@gmail.com']);
    }
}
