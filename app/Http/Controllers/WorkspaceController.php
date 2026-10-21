<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Services\OutletContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspaceController extends Controller
{
    public function index(Request $request, OutletContext $context): View
    {
        return view('workspace', ['outlet' => $context->current($request)]);
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
