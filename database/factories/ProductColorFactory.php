<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\ProductColor> */
class ProductColorFactory extends Factory
{
    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'name' => fake()->unique()->bothify('Warna-????##'),
            'color_hex' => '#233c69', 'sort_order' => 0, 'is_active' => true];
    }
}
