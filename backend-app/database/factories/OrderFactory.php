<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Store;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'customer']),
            'store_id' => Store::factory(),
            'status' => $this->faker->randomElement(['pending','confirmed','shipped','completed','cancelled']),
            'total_price' => $this->faker->randomFloat(2, 50, 1000),
            'created_at' => now(),
        ];
    }
}
