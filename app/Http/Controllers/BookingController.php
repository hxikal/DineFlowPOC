<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Http\Requests\CancelBookingRequest;
use App\Models\Booking;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Services\BookingManager;
use App\Services\OutletContext;
use App\Services\TableAvailability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    private function outlet(Request $request): Outlet
    {
        $outlet = app(OutletContext::class)->current($request);
        if (! $outlet) {
            throw new HttpResponseException(redirect()->route('bookings.index')->withErrors(['outlet_id' => __('booking.choose_outlet')]));
        }

        return $outlet;
    }

    private function scoped(Request $request, int $id): Booking
    {
        $outlet = $this->outlet($request);
        $booking = Booking::with(['assignments.table', 'creator', 'paymentMethod', 'outlet'])->where('outlet_id', $outlet->getKey())->findOrFail($id);
        Gate::authorize('view', [$booking, $outlet->getKey()]);

        return $booking;
    }

    public function index(Request $request): View
    {
        $filters = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:booked,cancelled']]);
        $outlet = app(OutletContext::class)->current($request);
        $date = $filters['date'] ?? $request->session()->get('dining_date', now()->toDateString());
        $query = Booking::with(['assignments.table'])->where('outlet_id', $outlet?->getKey() ?? 0)->whereDate('dining_date', $date);
        if (! empty($filters['status'])) {
            $query->where('booking_status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('booking_code', 'like', $term)->orWhere('customer_name', 'like', $term)->orWhere('contact_number', 'like', $term));
        }

        return view('bookings.index', ['bookings' => $query->orderByDesc('booking_id')->paginate(20)->withQueryString(), 'outlet' => $outlet, 'date' => $date]);
    }

    public function create(Request $request, TableAvailability $availability): View|RedirectResponse
    {
        $outlet = app(OutletContext::class)->current($request);
        if (! $outlet) {
            return redirect()->route('bookings.index')->withErrors(['outlet_id' => __('booking.choose_outlet')]);
        }
        Gate::authorize('create', [Booking::class, $outlet]);
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? $request->session()->get('dining_date', now()->toDateString());

        return $this->form($request, $outlet, null, $date, $availability);
    }

    private function form(Request $request, Outlet $outlet, ?Booking $booking, string $date, TableAvailability $availability): View
    {
        $oldDate = $request->old('dining_date', $date);
        $date = is_string($oldDate) && Validator::make(['date' => $oldDate], ['date' => 'date_format:Y-m-d'])->passes() ? $oldDate : $date;
        $tables = $availability->forDate($outlet->getKey(), $date, $booking);
        $seed = $booking?->assignments->whereNull('released_at')->map(fn ($row) => ['table_id' => $row->table_id, 'pax' => $row->pax])->values()->all() ?? [];
        $requested = $request->query('table');
        if (! $booking && is_scalar($requested) && $tables->contains(fn ($table) => $table['table_id'] === (int) $requested && $table['available'])) {
            $seed = [['table_id' => (int) $requested, 'pax' => 1]];
        }
        $selection = $request->old('tables', $seed);
        $selection = is_array($selection) ? array_values(array_filter($selection, fn ($row) => is_array($row) && isset($row['table_id'],$row['pax']) && is_scalar($row['table_id']) && is_scalar($row['pax']))) : [];
        $payments = PaymentMethod::where('status', 'active');
        if ($booking) {
            $payments->orWhere('payment_method_id', $booking->payment_method_id);
        }

        return view('bookings.form', ['outlet' => $outlet, 'booking' => $booking, 'date' => $date, 'tables' => $tables, 'selection' => $selection, 'payments' => $payments->orderBy('method_name')->get(), 'operationKey' => (string) Str::uuid()]);
    }

    public function store(BookingRequest $request, BookingManager $manager): RedirectResponse
    {
        $outlet = $this->outlet($request);
        $booking = $manager->create($request->user(), $outlet->getKey(), $request->validated());

        return redirect()->route('bookings.show', $booking->getKey())->with('success', __('booking.saved'));
    }

    public function show(Request $request, int $booking): View
    {
        return view('bookings.show', ['booking' => $this->scoped($request, $booking), 'cancelKey' => (string) Str::uuid()]);
    }

    public function edit(Request $request, int $booking, TableAvailability $availability): View
    {
        $record = $this->scoped($request, $booking);
        Gate::authorize('update', [$record, $record->outlet_id]);

        return $this->form($request, $this->outlet($request), $record, $record->dining_date->toDateString(), $availability);
    }

    public function update(BookingRequest $request, int $booking, BookingManager $manager): RedirectResponse
    {
        $record = $manager->update($request->user(), $this->outlet($request)->getKey(), $booking, $request->validated());

        return redirect()->route('bookings.show', $record->getKey())->with('success', __('booking.saved'));
    }

    public function cancel(CancelBookingRequest $request, int $booking, BookingManager $manager): RedirectResponse
    {
        $record = $manager->cancel($request->user(), $this->outlet($request)->getKey(), $booking, $request->validated());

        return redirect()->route('bookings.show', $record->getKey())->with('success', __('booking.cancelled_success'));
    }

    public function print(Request $request, int $booking): View
    {
        return view('bookings.print', ['booking' => $this->scoped($request, $booking)]);
    }
}
