<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingTable;
use App\Models\DiningTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TableAvailability
{
    public function forDate(int $outletId, string $date, ?Booking $editing = null): Collection
    {
        $retained = $editing && $editing->dining_date->toDateString() === $date && $date < now()->toDateString()
            ? BookingTable::where('booking_id', $editing->getKey())->whereNull('released_at')->pluck('table_id')->all() : [];
        $busy = DB::table('booking_tables')->join('dining_tables', 'dining_tables.table_id', '=', 'booking_tables.table_id')->where('dining_tables.outlet_id', $outletId)->where('booking_tables.dining_date', $date)->whereNull('booking_tables.released_at');
        if ($editing) {
            $busy->where('booking_tables.booking_id', '<>', $editing->getKey());
        }
        $busy = $busy->pluck('booking_tables.booking_id', 'booking_tables.table_id');

        return DiningTable::query()->leftJoin('outlet_sections', 'outlet_sections.section_id', '=', 'dining_tables.section_id')
            ->where('dining_tables.outlet_id', $outletId)->where(function ($query) use ($retained) {
                $query->where(function ($active) {
                    $active->where('dining_tables.status', 'active')->where(function ($s) {
                        $s->whereNull('dining_tables.section_id')->orWhere('outlet_sections.status', 'active');
                    });
                });
                if ($retained) {
                    $query->orWhereIn('dining_tables.table_id', $retained);
                }
            })->orderBy('outlet_sections.sort_order')->orderBy('dining_tables.sort_order')->orderBy('dining_tables.table_code')
            ->get(['dining_tables.table_id', 'dining_tables.table_code', 'outlet_sections.section_name', 'dining_tables.status'])
            ->map(fn ($t) => ['table_id' => (int) $t->table_id, 'table_code' => $t->table_code, 'section_name' => $t->section_name, 'available' => ! $busy->has($t->table_id), 'booking_id' => $busy->get($t->table_id), 'historical' => in_array($t->table_id, $retained, true)]);
    }
}
