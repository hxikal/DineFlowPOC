<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $primaryKey = 'booking_id';

    protected $guarded = ['booking_id'];

    protected function casts(): array
    {
        return ['dining_date' => 'date', 'cancelled_at' => 'datetime'];
    }
}
