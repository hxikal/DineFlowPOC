<?php

use App\Http\Controllers\PreviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('preview', ['screen' => 'dashboard']));
Route::get('/preview/{screen}', PreviewController::class)
    ->whereIn('screen', ['dashboard', 'booking', 'tables'])->name('preview');
Route::post('/locale', function (Request $request) {
    $data = $request->validate(['locale' => 'required|in:ms,en']);
    $request->session()->put('locale', $data['locale']);
    app()->setLocale($data['locale']);

    return response()->json(['locale' => $data['locale'], 'messages' => __('ui')]);
})->name('locale');
