<?php

namespace Database\Factories;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => \App\Models\Product::factory(),
            'month' => 10,
            'year' => 2026,
            'units_sold' => 10,
            'order_frequency' => 4,
            'unit_price' => '350000.00',
            'total_revenue' => '3500000.00',
        ];
    }
}
