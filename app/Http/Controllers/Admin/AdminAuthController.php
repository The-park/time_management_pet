<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

class AdminAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $request->ip().'|'.$request->input('email');

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ])->onlyInput('email');
        }

        $admin = Admin::where('email', $request->input('email'))->first();

        if (! $admin || ! Hash::check($request->input('password'), $admin->password)) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors([
                'email' => 'The provided credentials are incorrect.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);

        if (! $admin->two_factor_secret || ! $admin->two_factor_confirmed_at) {
            app(EnableTwoFactorAuthentication::class)($admin);
            $request->session()->put('admin.two_factor_setup_id', $admin->id);

            return redirect()->route('admin.two-factor.setup');
        }

        $request->session()->put('admin.two_factor_login_id', $admin->id);

        return redirect()->route('admin.two-factor.challenge');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
