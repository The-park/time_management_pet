<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
        RateLimiter::for('login', function (Request $request): Limit {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });

        RateLimiter::for('register', function (Request $request): Limit {
            return Limit::perHour(3)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            $email = (string) $request->input('email');

            return Limit::perHour(3)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Limit how often a user can request a fresh verification email — at
        // most six per hour to discourage abuse without locking out users who
        // genuinely need to retry once or twice.
        RateLimiter::for('verification.send', function (Request $request): Limit {
            $key = optional($request->user())->id ?: $request->ip();
            return Limit::perHour(6)->by($key);
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        // Custom authentication callback so we can short-circuit logins for
        // suspended accounts. Default Fortify only checks email + password and
        // ignores the `status` column, which means an admin "suspending" a user
        // had no effect on logins until now. SoftDeletes already handles deleted
        // users (the global scope hides them from email lookups).
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->input('email'))->first();

            if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
                return null; // Fortify will respond with the standard "credentials don't match" error.
            }

            if (($user->status ?? 'active') === 'suspended') {
                throw ValidationException::withMessages([
                    'email' => 'This account is suspended. Please contact support.',
                ]);
            }

            return $user;
        });

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn ($request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));

        // ── Branded transactional emails ───────────────────────────────────
        // Override Laravel's default plain-text verification + password-reset
        // notifications with the HTML templates in resources/views/emails/.
        // These run for both the auth flow (registration + forgot password)
        // and any admin-triggered resends from /admin/users/{id}.
        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            return (new MailMessage)
                ->subject('Verify your Track Your Time email')
                ->view('emails.verify-email', [
                    'url' => $url,
                    'user' => $notifiable,
                ]);
        });

        ResetPassword::toMailUsing(function ($notifiable, $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('Reset your Track Your Time password')
                ->view('emails.reset-password', [
                    'url' => $url,
                    'user' => $notifiable,
                ]);
        });
    }
}
