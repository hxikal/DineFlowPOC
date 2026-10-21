<?php

namespace Database\Factories;

use App\Models\DiningTable;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    public function definition(): array
    {
        return ['booking_code' => 'DF-'.now()->year.'-'.fake()->unique()->numerify('########'), 'table_id' => DiningTable::factory(), 'outlet_id' => fn (array $a) => DiningTable::findOrFail($a['table_id'])->outlet_id, 'dining_date' => now()->toDateString(), 'customer_name' => 'Tetamu Ujian', 'contact_number' => '010-0000000', 'pax' => 4, 'payment_method_id' => PaymentMethod::factory(), 'booking_status' => 'booked', 'created_by' => User::factory()];
    }
}
