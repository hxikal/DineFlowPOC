<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingTable;
use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Builder;

class BookingReports
{
    public const SORTS = ['dining_date', 'booking_code', 'customer_name', 'pax', 'booking_status'];

    public function query(?int $outletId, array $filters): Builder
    {
        $query = Booking::query()->where('outlet_id', $outletId ?? 0)->whereBetween('dining_date', [$filters['from'], $filters['to']]);
        foreach (['status' => 'booking_status', 'payment_method_id' => 'payment_method_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn (Builder $q) => $q->whereRaw("customer_name LIKE ? ESCAPE '!'", [$term])->orWhereRaw("contact_number LIKE ? ESCAPE '!'", [$term])->orWhereRaw("booking_code LIKE ? ESCAPE '!'", [$term]));
        }
        if (! empty($filters['table_id']) || ! empty($filters['section_id'])) {
            $query->whereHas('assignments', function (Builder $assignments) use ($filters): void {
                $assignments->where(fn (Builder $q) => $q->whereNull('released_at')->orWhere('bookings.booking_status', 'cancelled'));
                if (! empty($filters['table_id'])) {
                    $assignments->where('table_id', $filters['table_id']);
                }
                if (! empty($filters['section_id'])) {
                    $assignments->whereHas('table', fn (Builder $q) => $q->where('section_id', $filters['section_id']));
                }
            });
        }

        return $query;
    }

    public function ordered(Builder $query, array $filters): Builder
    {
        return $query->orderBy($filters['sort'], $filters['direction'])->orderBy('booking_id', $filters['direction']);
    }

    /** Booking-level aggregates deliberately never join assignment history. */
    public function summary(Builder $query): array
    {
        $rows = (clone $query)->selectRaw('booking_status, COUNT(*) AS bookings, COALESCE(SUM(pax), 0) AS pax')->groupBy('booking_status')->get()->keyBy('booking_status');

        return collect(['booked', 'cancelled'])->mapWithKeys(fn (string $status) => [$status => [
            'bookings' => (int) ($rows->get($status)?->bookings ?? 0), 'pax' => (int) ($rows->get($status)?->pax ?? 0),
        ]])->all();
    }

    public function dashboard(?int $outletId, string $date): array
    {
        $summary = $this->summary($this->query($outletId, ['from' => $date, 'to' => $date]));
        $eligible = DiningTable::query()->where('dining_tables.outlet_id', $outletId ?? 0)
            ->leftJoin('outlet_sections', 'outlet_sections.section_id', '=', 'dining_tables.section_id')
            ->where('dining_tables.status', 'active')->where(fn ($q) => $q->whereNull('dining_tables.section_id')->orWhere('outlet_sections.status', 'active'));
        $occupied = BookingTable::where('dining_date', $date)->whereNull('released_at')->whereHas('table', fn (Builder $q) => $q->where('outlet_id', $outletId ?? 0));
        $busy = (clone $eligible)->whereIn('dining_tables.table_id', (clone $occupied)->select('table_id'))->count();

        return ['booked' => $summary['booked']['bookings'], 'pax' => $summary['booked']['pax'], 'available' => $eligible->count() - $busy, 'busy' => $busy,
            'inactive_assignments' => $occupied->distinct()->count('table_id') - $busy];
    }

    public function analytics(Builder $query): array
    {
        $trend = (clone $query)->selectRaw('dining_date, booking_status, COUNT(*) AS bookings, SUM(pax) AS pax')->groupBy('dining_date', 'booking_status')->orderBy('dining_date')->orderBy('booking_status')->get();
        $payments = (clone $query)->selectRaw('payment_method_id, booking_status, COUNT(*) AS bookings, SUM(pax) AS pax')->with('paymentMethod')->groupBy('payment_method_id', 'booking_status')->orderBy('payment_method_id')->orderBy('booking_status')->get();

        return compact('trend', 'payments');
    }

    public function tableCodes(Booking $booking): string
    {
        $rows = $booking->booking_status === 'cancelled' ? $booking->assignments : $booking->assignments->whereNull('released_at');

        return $rows->pluck('table.table_code')->unique()->sort()->implode(', ');
    }

    /** Prefix spreadsheet formula text, including leading control/space characters. */
    public function csvText(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[\x00-\x20\x{00A0}]*[=+\-@]/u', $value) || preg_match('/^[\t\r\n]/', $value) ? "'".$value : $value;
    }
}
