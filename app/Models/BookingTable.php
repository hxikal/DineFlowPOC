<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class BookingTable extends Model
{
    use HasFactory;

    protected $primaryKey = 'booking_table_id';

    protected $guarded = ['booking_table_id', 'active_table_id'];

    protected function casts(): array
    {
        return ['dining_date' => 'date', 'released_at' => 'datetime', 'table_id' => 'integer'];
    }

    protected function diningDate(): Attribute
    {
        return Attribute::make(set: fn ($value) => Carbon::parse($value)->toDateString());
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id', 'table_id');
    }
}
