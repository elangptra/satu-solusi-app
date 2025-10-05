<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Buat user dummy untuk testing
        $this->user = User::factory()->create([
            'email'    => 'testuser@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_can_login_with_valid_credentials()
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'testuser@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
            ]);
    }

    public function test_fails_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'testuser@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Invalid Credentials']);
    }

    public function test_can_get_authenticated_user_with_valid_token()
    {
        $token = JWTAuth::fromUser($this->user);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->getJson('/api/getMe');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'email' => $this->user->email,
            ])
            ->assertJsonStructure([
                'id',
                'email',
                'role',
                'created_at',
                'updated_at',
            ]);
    }

    public function test_can_refresh_token()
    {
        $token = JWTAuth::fromUser($this->user);

        $response = $this->postJson('/api/refresh', [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
            ]);
    }

    public function test_can_logout_with_valid_token()
    {
        $token = JWTAuth::fromUser($this->user);

        $response = $this->postJson('/api/logout', [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Successfully logged out']);
    }
}
