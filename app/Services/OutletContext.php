<?php

namespace App\Services;

use App\Models\Outlet;
use Illuminate\Http\Request;

class OutletContext
{
    public function current(Request $request): ?Outlet
    {
        $id = $request->session()->get('outlet_id');
        $outlet = $id ? Outlet::where('status', 'active')->find($id) : null;
        if ($outlet) {
            return $outlet;
        }
        $request->session()->forget('outlet_id');

        return null;
    }
}
