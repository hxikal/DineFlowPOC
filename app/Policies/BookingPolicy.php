<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Outlet;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking, int $outletId): bool
    {
        return $user->status === 'active' && in_array($user->role, ['admin', 'user'], true) && $booking->outlet_id === $outletId;
    }

    public function create(User $user, Outlet $outlet): bool
    {
        return $user->status === 'active' && in_array($user->role, ['admin', 'user'], true) && $outlet->status === 'active';
    }

    public function update(User $user, Booking $booking, int $outletId): bool
    {
        return $this->view($user, $booking, $outletId) && $booking->booking_status === 'booked' && ($booking->dining_date->toDateString() >= now()->toDateString() || $user->isAdmin());
    }

    public function cancel(User $user, Booking $booking, int $outletId): bool
    {
        return $this->update($user, $booking, $outletId);
    }
}
