<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        // When a route-model-bound resource can't be resolved (deleted,
        // belongs to another user via the BelongsToUser global scope, or
        // never existed), bounce the user back to the resource's index
        // page with a friendly toast instead of leaking a 404.
        //
        // This fires for:
        //   - ModelNotFoundException (the underlying cause)
        //   - NotFoundHttpException (the wrapped form Laravel raises from
        //     SubstituteBindings)
        $resourceRedirect = function (Request $request) {
            if (! $request->user()) return null;     // let auth middleware handle
            if ($request->expectsJson()) return null; // APIs keep the 404

            if ($request->is('goals/*')) {
                return redirect()->route('goals.index')
                    ->with('toast', "That goal no longer exists, or you don't have access.");
            }
            return null; // fall through to default 404 page
        };

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($resourceRedirect) {
            return $resourceRedirect($request);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($resourceRedirect) {
            // Only intercept 404s that came from route-model binding, not
            // genuinely-missing routes (no such URL).
            if (! $e->getPrevious() instanceof ModelNotFoundException) return null;
            return $resourceRedirect($request);
        });

        // Policy denial on a /goals/* URL is functionally the same UX as
        // "goal not found" — the user has no business there. Redirect to
        // the index instead of showing a 403 dead-end. Laravel wraps
        // AuthorizationException in AccessDeniedHttpException before
        // render callbacks fire, so we match the wrapped form.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($resourceRedirect) {
            return $resourceRedirect($request);
        });
    })->create();
