<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OutletFactory extends Factory
{
    public function definition(): array
    {
        return ['outlet_code' => fake()->unique()->bothify('OUT-####??'), 'outlet_name' => 'Outlet Ujian '.fake()->unique()->bothify('####??'), 'status' => 'active', 'sort_order' => 0];
    }
}
