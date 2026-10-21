<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\OutletSection;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfigurationManager
{
    public const TYPES = ['outlets' => Outlet::class, 'sections' => OutletSection::class, 'tables' => DiningTable::class, 'payments' => PaymentMethod::class];

    public function __construct(private AuditWriter $audit) {}

    public function model(string $type): Model
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return new (self::TYPES[$type]);
    }

    public function save(User $actor, string $type, ?int $id, array $data, ?int $outletId): Model
    {
        return DB::transaction(function () use ($actor, $type, $id, $data, $outletId) {
            $currentActor = User::findOrFail($actor->getKey());
            abort_unless($currentActor->isAdmin(), 403);
            $model = $this->model($type);
            if (in_array($type, ['sections', 'tables'], true)) {
                $outlet = Outlet::whereKey($outletId)->where('status', 'active')->lockForUpdate()->firstOrFail();
                $data['outlet_id'] = $outlet->getKey();
                $model = $id ? $model->where('outlet_id', $outletId)->whereKey($id)->lockForUpdate()->firstOrFail() : $model;
            } else {
                $model = $id ? $model->whereKey($id)->lockForUpdate()->firstOrFail() : $model;
            }
            $before = $model->toArray();
            if ($type === 'tables' && ! empty($data['section_id'])) {
                $section = OutletSection::whereKey($data['section_id'])->where('outlet_id', $outletId)->lockForUpdate()->first();
                if (! $section || (($data['status'] ?? 'active') === 'active' && $section->status !== 'active')) {
                    throw ValidationException::withMessages(['section_id' => __('admin.invalid_section')]);
                }
            }
            if ($id && $type !== 'payments') {
                $affected = Booking::where('booking_status', 'booked')->whereDate('dining_date', '>=', now()->toDateString());
                if ($type === 'outlets') {
                    $affected->where('outlet_id', $id);
                } else {
                    $tables = DiningTable::where('outlet_id', $outletId);
                    $type === 'sections' ? $tables->where('section_id', $id) : $tables->where('table_id', $id);
                    $tableIds = $tables->orderBy('table_id')->lockForUpdate()->pluck('table_id');
                    $affected->where(function ($query) use ($tableIds) {
                        $query->whereIn('table_id', $tableIds)->orWhereIn('booking_id', DB::table('booking_tables')->whereIn('table_id', $tableIds)->whereNull('released_at')->select('booking_id'));
                    });
                }
                $blocking = $affected->orderBy('booking_id')->lockForUpdate()->pluck('booking_code');
                $moving = $type === 'tables' && array_key_exists('section_id', $data) && (int) $data['section_id'] !== (int) $model->section_id;
                if ((($data['status'] ?? $model->status) === 'inactive' || $moving) && $blocking->isNotEmpty()) {
                    throw ValidationException::withMessages(['status' => __('admin.bookings_block').': '.$blocking->take(20)->implode(', ')]);
                }
            }
            $model->fill($data);
            $model->save();
            $this->audit->record($currentActor, $id ? 'UPDATE_CONFIG' : 'CREATE_CONFIG', $type, $model->getKey(), $before, $model->toArray());

            return $model;
        }, 3);
    }
}
