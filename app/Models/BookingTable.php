<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingTable extends Model
{
    use HasFactory;

    protected $primaryKey = 'booking_table_id';

    protected $guarded = ['booking_table_id'];
}
