<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductColorSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::where('code', 'WL-W69-PRO')->first();
        if (! $product) {
            return;
        }
        foreach (['Pink' => '#e1adb7', 'Silver' => '#bdc2c8', 'Black' => '#25262a', 'Blue' => '#233c69'] as $name => $hex) {
            $product->colors()->withTrashed()->firstOrCreate(['name_key' => mb_strtolower($name)],
                ['name' => $name, 'color_hex' => $hex, 'sort_order' => array_search($name, ['Pink', 'Silver', 'Black', 'Blue'], true), 'is_active' => true]);
        }
    }
}
