<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use App\Services\CaptchaService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Replace Fortify's default RegisterResponse so a freshly registered
        // user always lands on the home page. The default uses
        // redirect()->intended(...), which sends them back to whatever URL
        // was stashed in the session — including /admin/* URLs that the
        // guest previously hit and got bounced from. That ends up routing
        // a brand-new (non-admin) user to the admin login screen, which is
        // confusing. The user dashboard is the right post-register landing.
        $this->app->singleton(RegisterResponseContract::class, function () {
            return new class implements RegisterResponseContract {
                public function toResponse($request): Response
                {
                    return $request->wantsJson()
                        ? response()->json('', 201)
                        : redirect()->to('/');
                }
            };
        });

        // After logout, send the user to the login screen with a flashed
        // toast ("You've been logged out — sign in again to keep tracking.").
        // The guest layout's toast renderer picks up `session('toast')` and
        // shows it as a transient notification. Default Fortify redirected
        // to '/' which silently lands authenticated-feeling UI for a logged-
        // out user — confusing and missed the chance to confirm the action.
        $this->app->singleton(LogoutResponseContract::class, function () {
            return new class implements LogoutResponseContract {
                public function toResponse($request): Response
                {
                    if ($request->wantsJson()) {
                        return response()->json('', 204);
                    }
                    return redirect()
                        ->route('login')
                        ->with('toast', "You've been logged out. Sign in again to keep tracking your day.");
                }
            };
        });
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

        // Contact-form throttle: cap at 5 submissions per hour per IP so a
        // single bot or angry user can't flood the inbox even if they
        // somehow defeat the CAPTCHA.
        RateLimiter::for('contact-form', function (Request $request): Limit {
            return Limit::perHour(5)->by($request->ip());
        });

        // Email-backup throttle. Keyed on user_id (with IP fallback for
        // somehow-unauthenticated requests) so two people behind a
        // shared NAT don't burn each other's quota. Five per hour is
        // ample for legitimate use and cheap to abuse-mitigate.
        RateLimiter::for('backup-send', function (Request $request): Limit {
            $key = optional($request->user())->id ?: $request->ip();
            return Limit::perHour(5)->by('backup-send|'.$key);
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
            // CAPTCHA gate first so a failed challenge doesn't even count
            // against the credential rate-limiter. The honeypot + math
            // challenge live in the same partial used by /register and
            // /contact, so a single source of truth here. verifyDetailed
            // distinguishes timing / proof-of-work / answer failures so the
            // message we surface is actionable.
            $captcha = app(CaptchaService::class);
            $result = $captcha->verifyDetailed(
                $request->input('captcha_token'),
                $request->input('captcha_answer'),
                $request->input('captcha_hp'),
                $request->input('captcha_pow_nonce'),
                $request->ip(),
            );
            if ($result !== 'ok') {
                $message = match ($result) {
                    'pow' => "Your browser couldn't complete the verification challenge. Refresh and try again.",
                    'timing' => 'Form submitted too quickly — please try again.',
                    'rate' => 'Too many CAPTCHA attempts from your network. Please wait a minute and try again.',
                    default => 'The CAPTCHA answer is incorrect. Please try the new one below.',
                };
                throw ValidationException::withMessages([
                    'captcha_answer' => $message,
                ]);
            }

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
                ->subject('Verify your Time Management Pet email')
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
                ->subject('Reset your Time Management Pet password')
                ->view('emails.reset-password', [
                    'url' => $url,
                    'user' => $notifiable,
                ]);
        });
    }
}
