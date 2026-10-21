<?php

namespace App\Providers;

use App\Support\DatabaseSafety;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
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
