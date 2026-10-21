<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Booking extends Model
{
    use HasFactory;

    protected $primaryKey = 'booking_id';

    protected $guarded = ['booking_id'];

    protected function casts(): array
    {
        return ['dining_date' => 'date', 'cancelled_at' => 'datetime', 'version' => 'integer', 'outlet_id' => 'integer', 'payment_method_id' => 'integer'];
    }

    protected function diningDate(): Attribute
    {
        return Attribute::make(set: fn ($value) => Carbon::parse($value)->toDateString());
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BookingTable::class, 'booking_id', 'booking_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id', 'payment_method_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'outlet_id');
    }
}
