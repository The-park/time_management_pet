<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // When the auth middleware fires for an unauthenticated request, we
        // need /admin/* routes to redirect to the admin login screen instead
        // of the regular user login.
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('admin', 'admin/*')) {
                return route('admin.login');
            }
            return route('login');
        });

        // Boot suspended users out of any active session on their next request.
        // Runs for the entire web group; no-op when there's no authenticated user.
        $middleware->web(append: [
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
