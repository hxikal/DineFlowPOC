<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingTable;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BookingManager
{
    public function __construct(private ReservationLocks $locks, private AuditWriter $audit) {}

    public static function rules(bool $editing = false): array
    {
        $rules = [
            'operation_key' => ['required', 'uuid'],
            'dining_date' => ['required', 'date_format:Y-m-d', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'customer_name' => ['required', 'string', 'max:150'],
            'contact_number' => ['required', 'string', 'max:30'],
            'payment_method_id' => ['required', 'integer', 'min:1'],
            'remark' => ['nullable', 'string', 'max:500'],
            'tables' => ['required', 'array', 'min:1', 'max:200'],
            'tables.*.table_id' => ['required', 'integer', 'min:1', 'distinct'],
            'tables.*.pax' => ['required', 'integer', 'min:1', 'max:999'],
            'correction_reason' => ['nullable', 'string', 'max:1000'],
        ];
        if ($editing) {
            $rules['version'] = ['required', 'integer', 'min:1'];
        }

        return $rules;
    }

    public static function cancelRules(): array
    {
        return ['operation_key' => ['required', 'uuid'], 'version' => ['required', 'integer', 'min:1'], 'cancellation_reason' => ['required', 'string', 'max:1000']];
    }

    private function actor(User $actor): User
    {
        $fresh = User::whereKey($actor->getKey())->sharedLock()->firstOrFail();
        abort_unless($fresh->status === 'active' && in_array($fresh->role, ['admin', 'user'], true) && ($actor->auth_version === null || $actor->auth_version === $fresh->auth_version), 403);

        return $fresh;
    }

    private function normalize(array $data): array
    {
        foreach (['customer_name', 'contact_number', 'remark', 'correction_reason', 'cancellation_reason'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = trim($data[$key]);
            }
        }
        if (isset($data['tables'])) {
            $data['tables'] = array_values(array_map(fn (array $row) => ['table_id' => (int) $row['table_id'], 'pax' => (int) $row['pax']], $data['tables']));
            usort($data['tables'], fn ($a, $b) => $a['table_id'] <=> $b['table_id']);
            $data['payment_method_id'] = (int) $data['payment_method_id'];
            $data['remark'] = $data['remark'] ?? null;
            $data['correction_reason'] = $data['correction_reason'] ?? null;
        }
        if (isset($data['version'])) {
            $data['version'] = (int) $data['version'];
        }

        return $data;
    }

    private function fingerprint(array $data, int $outletId, ?int $bookingId): string
    {
        return hash('sha256', json_encode([$outletId, $bookingId, Arr::except($data, ['operation_key'])], JSON_THROW_ON_ERROR));
    }

    private function replay(User $actor, string $operation, array $data, int $outletId, ?int $bookingId): ?Booking
    {
        $existing = DB::table('operation_keys')->where('user_id', $actor->getKey())->where('operation', $operation)->where('token_hash', hash('sha256', $data['operation_key']))->first();
        if (! $existing) {
            return null;
        }
        if (! hash_equals($existing->request_hash ?? '', $this->fingerprint($data, $outletId, $bookingId))) {
            throw ValidationException::withMessages(['operation_key' => __('booking.token_reused')]);
        }

        return Booking::where('outlet_id', $outletId)->findOrFail($existing->booking_id);
    }

    private function remember(User $actor, string $operation, array $data, Booking $booking, ?int $originalId): void
    {
        DB::table('operation_keys')->insert(['user_id' => $actor->getKey(), 'operation' => $operation, 'token_hash' => hash('sha256', $data['operation_key']), 'request_hash' => $this->fingerprint($data, $booking->outlet_id, $originalId), 'booking_id' => $booking->getKey(), 'created_at' => now()]);
    }

    private function number(string $date): string
    {
        $year = (int) substr($date, 0, 4);
        $prefix = 'DF-'.$year.'-';
        $new = DB::table('booking_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0, 'updated_at' => now()]);
        $sequence = DB::table('booking_sequences')->where('year', $year)->lockForUpdate()->firstOrFail();
        $last = (int) $sequence->last_number;
        if ($new) {
            foreach (Booking::where('booking_code', 'like', $prefix.'%')->pluck('booking_code') as $code) {
                if (preg_match('/^DF-'.$year.'-([0-9]+)$/D', $code, $match)) {
                    $last = max($last, (int) $match[1]);
                }
            }
        }
        DB::table('booking_sequences')->where('year', $year)->update(['last_number' => $last + 1, 'updated_at' => now()]);

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    private function checkDate(array $data, ?Booking $original = null): void
    {
        $historical = $original && $original->dining_date->toDateString() < now()->toDateString();
        if ($data['dining_date'] < now()->toDateString() && ! $historical) {
            throw ValidationException::withMessages(['dining_date' => __('booking.past_create')]);
        }
        if ($historical && empty($data['correction_reason'])) {
            throw ValidationException::withMessages(['correction_reason' => __('booking.reason_required')]);
        }
        if ($data['customer_name'] === '' || $data['contact_number'] === '') {
            throw ValidationException::withMessages(['customer_name' => __('booking.required_details')]);
        }
    }

    private function checkAvailability(array $data, ?int $except = null): void
    {
        $query = BookingTable::where('dining_date', $data['dining_date'])->whereNull('released_at')->whereIn('table_id', array_column($data['tables'], 'table_id'));
        if ($except) {
            $query->where('booking_id', '<>', $except);
        }
        if ($query->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['tables' => __('booking.table_conflict')]);
        }
    }

    private function payment(int $id, ?Booking $original = null): void
    {
        $method = PaymentMethod::whereKey($id)->sharedLock()->first();
        if (! $method || ($method->status !== 'active' && $original?->payment_method_id !== $id)) {
            throw ValidationException::withMessages(['payment_method_id' => __('booking.invalid_payment')]);
        }
    }

    public function snapshot(Booking $booking): array
    {
        $data = $booking->toArray();
        $data['dining_date'] = $booking->dining_date->toDateString();
        $data['assignments'] = BookingTable::where('booking_id', $booking->getKey())->orderBy('booking_table_id')->get(['table_id', 'pax', 'dining_date', 'released_at'])->toArray();

        return $data;
    }

    public function create(User $actor, int $outletId, array $input): Booking
    {
        $data = $this->normalize(Validator::make($input, self::rules())->validate());

        return DB::transaction(function () use ($actor, $outletId, $data) {
            $actor = $this->actor($actor);
            $outlet = Outlet::whereKey($outletId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', [Booking::class, $outlet]);
            if ($existing = $this->replay($actor, 'create_booking', $data, $outletId, null)) {
                return $existing;
            }
            $this->checkDate($data);
            $this->locks->acquire($outletId, array_column($data['tables'], 'table_id'));
            $this->payment($data['payment_method_id']);
            $this->checkAvailability($data);
            $booking = Booking::create(array_merge(Arr::only($data, ['dining_date', 'customer_name', 'contact_number', 'payment_method_id', 'remark']), ['booking_code' => $this->number($data['dining_date']), 'outlet_id' => $outletId, 'pax' => array_sum(array_column($data['tables'], 'pax')), 'table_id' => $data['tables'][0]['table_id'], 'booking_status' => 'booked', 'created_by' => $actor->getKey(), 'version' => 1]));
            foreach ($data['tables'] as $row) {
                BookingTable::create($row + ['booking_id' => $booking->getKey(), 'dining_date' => $data['dining_date']]);
            }
            $this->audit->record($actor, 'CREATE_BOOKING', 'booking', $booking->getKey(), [], $this->snapshot($booking));
            $this->remember($actor, 'create_booking', $data, $booking, null);

            return $booking->fresh();
        }, 3);
    }

    public function update(User $actor, int $outletId, int $id, array $input): Booking
    {
        $data = $this->normalize(Validator::make($input, self::rules(true))->validate());

        return DB::transaction(function () use ($actor, $outletId, $id, $data) {
            $actor = $this->actor($actor);
            Outlet::whereKey($outletId)->lockForUpdate()->firstOrFail();
            $booking = Booking::where('outlet_id', $outletId)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($existing = $this->replay($actor, 'update_booking:'.$id, $data, $outletId, $id)) {
                return $existing;
            }
            Gate::forUser($actor)->authorize('update', [$booking, $outletId]);
            if ($booking->version !== $data['version']) {
                throw ValidationException::withMessages(['version' => __('booking.stale')])->status(409);
            }
            $this->checkDate($data, $booking);
            $historical = $booking->dining_date->toDateString() < now()->toDateString();
            $retained = $historical && $booking->dining_date->toDateString() === $data['dining_date'] ? BookingTable::where('booking_id', $id)->whereNull('released_at')->pluck('table_id')->all() : [];
            $this->locks->acquire($outletId, array_column($data['tables'], 'table_id'), $retained);
            $this->payment($data['payment_method_id'], $booking);
            $this->checkAvailability($data, $id);
            $before = $this->snapshot($booking);
            $active = BookingTable::where('booking_id', $id)->whereNull('released_at')->orderBy('booking_table_id')->lockForUpdate()->get();
            foreach ($active as $assignment) {
                $row = collect($data['tables'])->firstWhere('table_id', $assignment->table_id);
                if (! $row || $assignment->dining_date->toDateString() !== $data['dining_date'] || (int) $assignment->pax !== $row['pax']) {
                    $assignment->released_at = now();
                    $assignment->save();
                }
            }
            foreach ($data['tables'] as $row) {
                if (! BookingTable::where('booking_id', $id)->where('table_id', $row['table_id'])->whereNull('released_at')->exists()) {
                    BookingTable::create($row + ['booking_id' => $id, 'dining_date' => $data['dining_date']]);
                }
            }
            $booking->fill(Arr::only($data, ['dining_date', 'customer_name', 'contact_number', 'payment_method_id', 'remark']));
            $booking->pax = array_sum(array_column($data['tables'], 'pax'));
            $booking->table_id = $data['tables'][0]['table_id'];
            $booking->updated_by = $actor->getKey();
            $booking->version++;
            $booking->save();
            $after = $this->snapshot($booking);
            if ($historical) {
                $after['correction_reason'] = $data['correction_reason'];
            }
            $this->audit->record($actor, $historical ? 'CORRECT_PAST_BOOKING' : 'UPDATE_BOOKING', 'booking', $id, $before, $after);
            $this->remember($actor, 'update_booking:'.$id, $data, $booking, $id);

            return $booking->fresh();
        }, 3);
    }

    public function cancel(User $actor, int $outletId, int $id, array $input): Booking
    {
        $data = $this->normalize(Validator::make($input, self::cancelRules())->validate());
        if ($data['cancellation_reason'] === '') {
            throw ValidationException::withMessages(['cancellation_reason' => __('booking.reason_required')]);
        }

        return DB::transaction(function () use ($actor, $outletId, $id, $data) {
            $actor = $this->actor($actor);
            Outlet::whereKey($outletId)->lockForUpdate()->firstOrFail();
            $booking = Booking::where('outlet_id', $outletId)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($existing = $this->replay($actor, 'cancel_booking:'.$id, $data, $outletId, $id)) {
                return $existing;
            }
            Gate::forUser($actor)->authorize('cancel', [$booking, $outletId]);
            if ($booking->version !== $data['version']) {
                throw ValidationException::withMessages(['version' => __('booking.stale')])->status(409);
            }
            $before = $this->snapshot($booking);
            BookingTable::where('booking_id', $id)->whereNull('released_at')->update(['released_at' => now(), 'updated_at' => now()]);
            $booking->fill(['booking_status' => 'cancelled', 'cancelled_by' => $actor->getKey(), 'cancelled_at' => now(), 'cancellation_reason' => $data['cancellation_reason'], 'updated_by' => $actor->getKey(), 'version' => $booking->version + 1]);
            $booking->save();
            $this->audit->record($actor, 'CANCEL_BOOKING', 'booking', $id, $before, $this->snapshot($booking));
            $this->remember($actor, 'cancel_booking:'.$id, $data, $booking, $id);

            return $booking->fresh();
        }, 3);
    }
}
