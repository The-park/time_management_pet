<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * If the currently-authenticated user has been suspended (status='suspended')
 * by an admin, log them out on their next request and redirect to login with
 * a clear message. Soft-deleted users are already handled automatically by
 * the SoftDeletes global scope on the User model — auth() returns null for
 * them — so this middleware only needs to handle the suspended case.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->status ?? 'active') === 'suspended') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'This account is suspended. Please contact support.']);
        }

        return $next($request);
    }
}
