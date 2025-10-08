<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserManipTest extends TestCase
{
    use RefreshDatabase;

    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        // Buat user dan login untuk dapat JWT token
        $user = User::factory()->create();
        $this->token = JWTAuth::fromUser($user);
    }

    /**
     * Helper untuk kirim request dengan header JWT
     */
    protected function withTokenHeader(array $headers = [])
    {
        return array_merge($headers, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);
    }

    /** @test */
    public function test_it_can_get_all_users_with_profiles()
    {
        User::factory()->count(3)->create()->each(function ($user) {
            UserProfile::factory()->create(['user_id' => $user->id]);
        });

        $response = $this->getJson('/api/users', $this->withTokenHeader());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'users' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'profile'
                    ]
                ]
            ]);
    }

    /** @test */
    public function test_it_can_show_user_by_id()
    {
        $user = User::factory()->create();
        UserProfile::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/users/{$user->id}", $this->withTokenHeader());

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'profile'
                ]
            ]);
    }

    /** @test */
    public function test_it_returns_404_if_user_not_found()
    {
        $response = $this->getJson('/api/users/999', $this->withTokenHeader());

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'User tidak ditemukan',
            ]);
    }

    /** @test */
    public function test_it_can_create_user_with_profile_and_photo()
    {
        // Siapkan file palsu untuk upload (tanpa GD)
        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        // Lakukan request POST ke endpoint store user
        $response = $this->post('/api/users', [
            'name' => 'Putra',
            'email' => 'putra@example.com',
            'role' => 'merchant',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Jl. Semanggi Timur, Malang',
            'phone' => '081234567890',
            'photo' => $file,
        ], $this->withTokenHeader());

        // Pastikan response sukses
        $response->assertStatus(201)
            ->assertJsonFragment([
                'message' => 'User baru berhasil dibuat',
            ])
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'profile' => [
                        'user_id',
                        'photo_url',
                        'address',
                        'phone',
                    ],
                ],
            ]);

        // Pastikan file tersimpan di storage/public/uploads/profile_photos
        $files = Storage::disk('public')->files('uploads/profile_photos');
        $this->assertTrue(count($files) > 0, 'Photo file was not stored in uploads/profile_photos');

        // Pastikan user tersimpan di database
        $this->assertDatabaseHas('users', [
            'email' => 'putra@example.com',
            'role' => 'merchant',
        ]);

        // Pastikan profile tersimpan dengan relasi ke user
        $user = \App\Models\User::where('email', 'putra@example.com')->first();
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'address' => 'Jl. Semanggi Timur, Malang',
            'phone' => '081234567890',
        ]);
    }

    /** @test */
    public function test_it_can_update_user_with_profile_and_photo()
    {
        $user = User::factory()->create();
        UserProfile::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');

        $response = $this->post("/api/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
            'address' => 'New Address',
            'phone' => '08123456789',
            'photo' => $file,
        ], $this->withTokenHeader());

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Data user berhasil diperbarui',
            ]);

        $files = Storage::disk('public')->files('uploads/profile_photos');
        $this->assertTrue(count($files) > 0, 'Photo file was not stored in uploads/profile_photos');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated@example.com',
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'address' => 'New Address',
            'phone' => '08123456789',
        ]);
    }

    /** @test */
    public function test_it_can_delete_user_and_profile_with_photo()
    {
        $user = User::factory()->create();
        $photoPath = 'uploads/profile_photos/test_photo.jpg';

        Storage::disk('public')->put($photoPath, 'fake content');

        UserProfile::factory()->create([
            'user_id' => $user->id,
            'photo_url' => '/storage/' . $photoPath,
        ]);

        $response = $this->withHeaders($this->withTokenHeader())
            ->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'User berhasil dihapus']);

        $this->assertFalse(Storage::disk('public')->exists($photoPath));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('user_profiles', ['user_id' => $user->id]);
    }
}
