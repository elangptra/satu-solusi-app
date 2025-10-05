<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

class StoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'merchant']),
            'name' => $this->faker->company(),
            'photo_url' => $this->faker->imageUrl(),
            'address' => $this->faker->address(),
            'description' => $this->faker->catchPhrase(),
            'created_at' => now(),
        ];
    }
}
