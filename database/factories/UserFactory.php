<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Pengguna Ujian', 'username' => fake()->unique()->userName(), 'password' => Hash::make('test-passphrase-123'), 'role' => 'user', 'department' => 'Hostess', 'status' => 'active', 'preferred_locale' => 'ms'];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
