<?php

namespace App\Providers;

use App\Models\Outlet;
use App\Policies\AdministrationPolicy;
use App\Support\DatabaseSafety;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('administer', [AdministrationPolicy::class, 'manage']);
        View::composer('layouts.app', function ($view): void {
            $view->with('activeOutlets', Outlet::where('status', 'active')->orderBy('sort_order')->orderBy('outlet_name')->get());
        });
        $name = config('database.default');
        app(DatabaseSafety::class)->assertSafe(config("database.connections.$name", []), app()->environment());
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            $event->connection->beforeExecuting(function (string $query, array $bindings, Connection $connection): void {
                app(DatabaseSafety::class)->assertSafe($connection->getConfig(), app()->environment());
            });
            app(DatabaseSafety::class)->assertSafe($event->connection->getConfig(), app()->environment());
        });
    }
}
