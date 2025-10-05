<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        // Buat user dummy
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'), // password = "password"
            'role' => 'customer',
        ]);

        // Buat profil untuk user tersebut
        UserProfile::create([
            'user_id' => $user->id,
            'photo_url' => 'https://via.placeholder.com/150',
            'address' => 'Jl. Dummy No.123, Jakarta',
            'phone' => '081234567890',
        ]);
    }
}
