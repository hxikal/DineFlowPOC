<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TableBoardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkspaceController;
use App\Models\ActivityLog;
use App\Services\ConfigurationManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'workspace' : 'login'));
Route::get('/preview/{screen}', PreviewController::class)->whereIn('screen', ['dashboard', 'booking', 'tables'])->name('preview');
Route::post('/locale', LocaleController::class)->name('locale');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:30,1')->name('login.store');
});
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/csv', [ReportController::class, 'csv'])->name('reports.csv');
    Route::get('/reports/print', [ReportController::class, 'print'])->name('reports.print');
    Route::get('/analytics', [ReportController::class, 'analytics'])->name('analytics');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->whereNumber('booking')->name('bookings.show');
    Route::get('/bookings/{booking}/edit', [BookingController::class, 'edit'])->whereNumber('booking')->name('bookings.edit');
    Route::put('/bookings/{booking}', [BookingController::class, 'update'])->whereNumber('booking')->name('bookings.update');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->whereNumber('booking')->name('bookings.cancel');
    Route::get('/bookings/{booking}/print', [BookingController::class, 'print'])->whereNumber('booking')->name('bookings.print');
    Route::get('/table-board', [TableBoardController::class, 'index'])->name('table-board');
    Route::get('/table-availability', [TableBoardController::class, 'availability'])->name('tables.availability');
    Route::get('/workspace', [WorkspaceController::class, 'index'])->name('workspace');
    Route::post('/context', [WorkspaceController::class, 'select'])->name('context');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/account/password', [AuthController::class, 'password'])->name('password.edit');
    Route::put('/account/password', [AuthController::class, 'updatePassword'])->name('password.update');
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->whereNumber('user')->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
        Route::put('/users/{user}/password', [UserController::class, 'reset'])->whereNumber('user')->name('users.reset');
        Route::get('/configuration/{type}', [ConfigurationController::class, 'index'])->whereIn('type', array_keys(ConfigurationManager::TYPES))->name('configuration.index');
        Route::get('/configuration/{type}/{record}/edit', [ConfigurationController::class, 'index'])->whereIn('type', array_keys(ConfigurationManager::TYPES))->whereNumber('record')->name('configuration.edit');
        Route::post('/configuration/{type}', [ConfigurationController::class, 'store'])->whereIn('type', array_keys(ConfigurationManager::TYPES))->name('configuration.store');
        Route::put('/configuration/{type}/{record}', [ConfigurationController::class, 'update'])->whereIn('type', array_keys(ConfigurationManager::TYPES))->whereNumber('record')->name('configuration.update');
        Route::get('/audit', fn () => view('admin.audit', ['logs' => ActivityLog::orderByDesc('activity_id')->paginate(30)]))->name('audit.index');
    });
});
