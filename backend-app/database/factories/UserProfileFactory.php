<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\UserProfile;

class UserProfileFactory extends Factory
{

    protected $model = UserProfile::class;

    public function definition(): array
    {
        return [
            // Otomatis membuat user baru kalau belum ada
            'user_id' => User::factory(),

            // URL foto palsu
            'photo_url' => '/storage/uploads/profile_photos/' . $this->faker->uuid() . '.jpg',

            // Data profil palsu
            'address' => $this->faker->address(),
            'phone'   => $this->faker->phoneNumber(),
        ];
    }
}
