<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductImageFactory extends Factory
{
    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'path' => 'products/'.fake()->uuid().'.webp', 'alt_text' => 'Foto produk', 'sort_order' => 0];
    }
}
