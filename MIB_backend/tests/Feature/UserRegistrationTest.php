<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
    public function user_can_register_successfully()
    {
        $response = $this->postJson('/api/register', [
            'email' => 'john23@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['email' => 'john23@example.com']);
    }  

    // /** @test */
    // public function registration_fails_with_invalid_data()
    // {
    //     $response = $this->postJson('/api/register', [
    //         'email' => 'not-an-email',
    //         'password' => 'short',
    //         'password_confirmation' => 'notmatching',
    //     ]);

    //     $response->assertStatus(422);
    //     $response->assertJsonValidationErrors([ 'email', 'password']);
    // }
}
