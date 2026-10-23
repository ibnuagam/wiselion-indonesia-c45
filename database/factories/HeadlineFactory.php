<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class HeadlineFactory extends Factory
{
    public function definition(): array
    {
        return ['title' => fake()->sentence(3), 'eyebrow' => 'WISELION COLLECTION', 'description' => fake()->sentence(), 'button_text' => 'Jelajahi koleksi', 'theme' => 'dark', 'sort_order' => 0, 'is_published' => false];
    }
}
