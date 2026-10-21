<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\OutletContext;
use App\Services\TableAvailability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TableBoardController extends Controller
{
    public function index(Request $request, OutletContext $context, TableAvailability $availability): View
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $data['date'] ?? $request->session()->get('dining_date', now()->toDateString());
        $outlet = $context->current($request);

        return view('bookings.board', ['outlet' => $outlet, 'date' => $date, 'tables' => $outlet ? $availability->forDate($outlet->getKey(), $date) : collect()]);
    }

    public function availability(Request $request, OutletContext $context, TableAvailability $availability): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'except' => ['nullable', 'integer', 'min:1']]);
        $outlet = $context->current($request);
        abort_unless($outlet, 409);
        $editing = empty($data['except']) ? null : Booking::where('outlet_id', $outlet->getKey())->findOrFail($data['except']);
        if ($editing) {
            Gate::authorize('update', [$editing, $outlet->getKey()]);
        }

        return response()->json(['date' => $data['date'], 'tables' => $availability->forDate($outlet->getKey(), $data['date'], $editing)]);
    }
}
