<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PreviewController extends Controller
{
    public function __invoke(string $screen): View
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        return view('preview', [
            'screen' => $screen,
            'bookings' => [
                ['code' => 'DEMO-001', 'name' => 'Tetamu Demo A', 'tables' => 'A01, A02', 'pax' => 8],
                ['code' => 'DEMO-002', 'name' => 'Tetamu Demo B', 'tables' => 'A05', 'pax' => 4],
                ['code' => 'DEMO-003', 'name' => 'Tetamu Demo C', 'tables' => 'A08', 'pax' => 6],
            ],
        ]);
    }
}
