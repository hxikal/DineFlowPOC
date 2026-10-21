<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingTableFactory extends Factory
{
    public function definition(): array
    {
        return ['booking_id' => Booking::factory(), 'table_id' => fn (array $a) => Booking::findOrFail($a['booking_id'])->table_id, 'dining_date' => fn (array $a) => Booking::findOrFail($a['booking_id'])->dining_date, 'pax' => 4, 'released_at' => null];
    }
}
