<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Services\BookingReports;
use App\Services\OutletContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspaceController extends Controller
{
    public function index(Request $request, OutletContext $context, BookingReports $reports): View
    {
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'page' => ['nullable', 'integer', 'min:1']])['date'] ?? $request->session()->get('dining_date', now()->toDateString());
        $outlet = $context->current($request);

        return DB::transaction(function () use ($date, $outlet, $reports): View {
            return view('workspace', ['outlet' => $outlet, 'date' => $date, 'metrics' => $reports->dashboard($outlet?->getKey(), $date),
                'bookings' => $reports->query($outlet?->getKey(), ['from' => $date, 'to' => $date])->with(['assignments.table', 'paymentMethod'])->orderBy('booking_code')->orderBy('booking_id')->paginate(20)->appends(['date' => $date]), 'reports' => $reports]);
        });
    }

    public function select(Request $request): RedirectResponse
    {
        $data = $request->validate(['outlet_id' => ['required', 'integer'], 'dining_date' => ['nullable', 'date_format:Y-m-d']]);
        DB::transaction(function () use ($request, $data) {
            $outlet = Outlet::whereKey($data['outlet_id'])->where('status', 'active')->lockForUpdate()->firstOrFail();
            $request->session()->put('outlet_id', $outlet->getKey());
            if (! empty($data['dining_date'])) {
                $request->session()->put('dining_date', $data['dining_date']);
            }
        });

        return back()->with('success', __('admin.context_saved'));
    }
}
