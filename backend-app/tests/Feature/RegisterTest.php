<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_with_valid_data()
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'Test User',
            'email'                 => 'newuser@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'customer',
        ]);

        $response->dump();

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
                'token' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
        ]);
    }

    public function test_fails_register_with_invalid_email()
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'Test User',
            'email'                 => 'invalid-email',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'customer',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_fails_register_with_password_mismatch()
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'Test User',
            'email'                 => 'anotheruser@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'differentPassword',
            'role'                  => 'customer',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }
}