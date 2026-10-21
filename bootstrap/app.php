<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class]);
        $middleware->alias(['active' => EnsureActiveUser::class, 'admin' => EnsureAdmin::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('workspace'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password']);
        $exceptions->report(function (QueryException $exception): bool {
            Log::error('Database operation failed.', ['sqlstate' => (string) $exception->getCode()]);

            return false;
        });
        $exceptions->render(function (QueryException $exception, Request $request) {
            $status = (string) $exception->getCode() === '23000' ? 422 : 500;
            if ($request->expectsJson()) {
                return response()->json(['message' => __('admin.database_error')], $status);
            }
            if ($status === 422) {
                return back()->withErrors(['record' => __('admin.database_error')])->withInput($request->except(['password', 'password_confirmation', 'current_password', '_token']));
            }

            return response()->view('errors.layout', ['code' => 500], 500);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
