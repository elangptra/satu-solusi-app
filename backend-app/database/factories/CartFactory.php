<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Store;

class CartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'customer']),
            'store_id' => Store::factory(),
            'created_at' => now(),
        ];
    }
}
