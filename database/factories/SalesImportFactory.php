<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\SalesImport> */
class SalesImportFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'source_name' => 'sales.csv', 'checksum' => hash('sha256', 'test'),
            'data_origin' => 'simulation', 'status' => 'preview', 'rows' => [], 'errors' => [], 'row_count' => 0];
    }
}
