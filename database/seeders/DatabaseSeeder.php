<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create(['name' => 'Admin Wiselion', 'email' => 'admin@wiselion.test', 'password' => 'password', 'role' => 'admin']);
        User::factory()->create(['name' => 'Manager Wiselion', 'email' => 'manager@wiselion.test', 'password' => 'password', 'role' => 'manager']);
        User::factory()->create(['name' => 'Pelanggan Wiselion', 'email' => 'pelanggan@wiselion.test', 'password' => 'password', 'role' => 'pelanggan']);
        $categories = collect(['Elektronik', 'Aksesoris', 'Peralatan Kantor'])->mapWithKeys(fn (string $name): array => [$name => Category::create(['name' => $name])]);
        $products = collect([
            ['EL-001', 'Laptop Pro 14', 'Elektronik', 12500000, 85, 32],
            ['EL-002', 'Monitor Vision 24', 'Elektronik', 2800000, 48, 20],
            ['AK-001', 'Keyboard Mechanical', 'Aksesoris', 850000, 22, 11],
            ['AK-002', 'Mouse Wireless', 'Aksesoris', 350000, 7, 4],
            ['PK-001', 'Kursi Ergonomis', 'Peralatan Kantor', 1900000, 3, 2],
        ])->each(function (array $item) use ($categories): void {
            $product = Product::create(['category_id' => $categories[$item[2]]->id, 'code' => $item[0], 'name' => $item[1], 'price' => $item[3], 'description' => 'Produk simulasi Wiselion', 'is_active' => true]);
            Sale::create(['product_id' => $product->id, 'month' => 10, 'year' => 2026, 'units_sold' => $item[4], 'order_frequency' => $item[5], 'unit_price' => $item[3], 'total_revenue' => $item[3] * $item[4]]);
        });
    }
}
