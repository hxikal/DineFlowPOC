<?php

namespace App\Services;

use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\OutletSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationLocks
{
    public function acquire(int $outletId, array $tableIds): Collection
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Reservation locks require a transaction.');
        }
        $ids = array_values(array_unique(array_map('intval', $tableIds)));
        sort($ids);
        if (! $ids || in_array(0, $ids, true)) {
            throw ValidationException::withMessages(['tables' => __('admin.invalid_section')]);
        }
        Outlet::whereKey($outletId)->where('status', 'active')->lockForUpdate()->firstOrFail();
        $sectionIds = DiningTable::where('outlet_id', $outletId)->whereIn('table_id', $ids)->whereNotNull('section_id')->pluck('section_id');
        $sections = OutletSection::where('outlet_id', $outletId)->whereIn('section_id', $sectionIds)->orderBy('section_id')->lockForUpdate()->get();
        $tables = DiningTable::where('outlet_id', $outletId)->whereIn('table_id', $ids)->orderBy('table_id')->lockForUpdate()->get();
        if ($tables->count() !== count($ids) || $tables->contains(fn ($t) => $t->status !== 'active') || $sections->contains(fn ($s) => $s->status !== 'active')) {
            throw ValidationException::withMessages(['tables' => __('admin.invalid_section')]);
        }

        return $tables;
    }
}
