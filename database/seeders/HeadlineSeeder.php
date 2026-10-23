<?php

namespace Database\Seeders;

use App\Models\Headline;
use Illuminate\Database\Seeder;

class HeadlineSeeder extends Seeder
{
    public function run(): void
    {
        Headline::firstOrCreate(['title' => 'Waktu bergerak.\nGaya mengikuti.'], ['eyebrow' => 'THE WISELION COLLECTION', 'description' => 'Temukan smartwatch untuk ritme harian, aktivitas, dan gaya personal Anda.', 'button_text' => 'Temukan jam Anda', 'theme' => 'dark', 'sort_order' => 1, 'is_published' => true]);
        Headline::firstOrCreate(['title' => 'Pilihan modern.\nUntuk setiap hari.'], ['eyebrow' => 'MADE FOR YOUR EVERYDAY', 'description' => 'Jelajahi koleksi Wiselion dan pilih jam tangan sesuai anggaran Anda.', 'button_text' => 'Jelajahi koleksi', 'theme' => 'light', 'sort_order' => 2, 'is_published' => true]);
    }
}
