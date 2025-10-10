<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Store;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'stock' => $this->faker->numberBetween(0, 100),
            'category' => $this->faker->word(),
            'photo_url' => $this->faker->imageUrl(640, 480, 'product', true, 'Faker'),
            'is_active' => true,
            'created_at' => now(),
        ];
    }
}
