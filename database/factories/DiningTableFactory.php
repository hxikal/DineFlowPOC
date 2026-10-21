<?php

namespace Database\Factories;

use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiningTableFactory extends Factory
{
    public function definition(): array
    {
        return ['outlet_id' => Outlet::factory(), 'section_id' => null, 'table_code' => fake()->unique()->bothify('T-####??'), 'table_number' => 1, 'status' => 'active', 'sort_order' => 0];
    }
}
