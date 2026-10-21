<?php

namespace Database\Factories;

use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

class OutletSectionFactory extends Factory
{
    public function definition(): array
    {
        return ['outlet_id' => Outlet::factory(), 'section_code' => fake()->unique()->bothify('S-####??'), 'section_name' => 'Seksyen Ujian', 'status' => 'active', 'sort_order' => 0];
    }
}
