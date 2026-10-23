<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class WiselionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::firstOrCreate(['name' => 'Smartwatch Wiselion']);

        $products = [
            ['WL-W69-PRO', 'WISELION W69 ProMax', 271000, 'Smartwatch AMOLED dengan GPS, NFC, Bluetooth call, serta lebih dari 130 fungsi olahraga dan kesehatan.'],
            ['WL-LG69-PRO', 'WISELION LG69 ProMax', 271000, 'Smartwatch dengan NFC, GPS, pemantauan olahraga dan kesehatan, serta dukungan panggilan Bluetooth.'],
            ['WL-W99', 'WISELION W99 Sports', 323000, 'Smartwatch olahraga untuk pria dan wanita dengan Bluetooth call, efek 3D, serta dukungan aktivitas renang.'],
            ['WL-W9-PRO', 'WISELION W9 Pro', 196000, 'Smartwatch fashion dengan layar AMOLED 2.01 inci, pemantauan kesehatan, olahraga, tidur, dan oksimeter.'],
            ['WL-LG69-MINI', 'WISELION LG69 Mini', 241000, 'Smartwatch ringkas dengan layar AMOLED HD 1.83 inci, mode olahraga, kesehatan, dan notifikasi.'],
            ['WL-LG79', 'WISELION LG79', 277000, 'Smartwatch Bluetooth dengan layar AMOLED 2.01 inci untuk pemakaian harian dan pemantauan kesehatan.'],
            ['WL-GT4', 'WISELION GT4', 1666000, 'Smartwatch dengan layar 1.52 inci, Bluetooth call dan musik, IP68, baterai hingga 7 hari, serta lebih dari 100 mode olahraga.'],
        ];

        foreach ($products as [$code, $name, $price, $description]) {
            Product::updateOrCreate(
                ['code' => $code],
                ['category_id' => $category->id, 'name' => $name, 'price' => $price, 'description' => $description, 'is_active' => true],
            );
        }
    }
}
