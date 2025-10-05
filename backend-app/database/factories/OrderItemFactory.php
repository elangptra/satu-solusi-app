<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Order;
use App\Models\Product;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $price = $this->faker->randomFloat(2, 10, 500);
        $qty = $this->faker->numberBetween(1, 5);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => $this->faker->word(),
            'price_at_purchase' => $price,
            'quantity' => $qty,
            // subtotal dihitung otomatis di migration PostgreSQL (GENERATED ALWAYS)
        ];
    }
}
