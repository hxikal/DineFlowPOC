<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'action' => 'TEST_EVENT', 'reference_type' => 'test', 'reference_id' => null, 'description' => 'Synthetic test event'];
    }
}
