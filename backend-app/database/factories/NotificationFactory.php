<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'message' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(['order_created','order_confirmed','order_done','new_order','low_stock','product_deleted']),
            'related_type' => $this->faker->randomElement(['order','product','system']),
            'related_id' => $this->faker->randomNumber(),
            'is_read' => false,
            'created_at' => now(),
        ];
    }
}
