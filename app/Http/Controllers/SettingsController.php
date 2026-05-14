<?php

namespace App\Http\Controllers;

use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function show(Request $request)
    {
        // ── Email-backup readiness gate ─────────────────────────────
        // The /settings view has an optional "Email backup" panel that's
        // only legal to render when BOTH the DB columns AND the routes
        // are in place. If a partial deploy happens (e.g. new view
        // uploaded but `php artisan route:cache` not yet refreshed, or
        // migration pending), calling `route('backup.send')` from the
        // view throws RouteNotFoundException → 500. Compute the flag
        // here and let the view skip the section instead of crashing.
        //
        // Try/catch on Schema::hasColumn so a DB hiccup degrades to
        // "feature hidden" rather than re-throwing — settings is a
        // non-critical page; we'd rather render without one section
        // than show a 500.
        $backupFeatureReady = false;
        try {
            $backupFeatureReady = Schema::hasColumn('users', 'backup_email_enabled')
                && Route::has('backup.send')
                && Route::has('backup.config');
        } catch (\Throwable $e) {
            $backupFeatureReady = false;
        }

        return view('settings', [
            'user' => $request->user(),
            'backupFeatureReady' => $backupFeatureReady,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'end_of_day_time' => ['required', 'date_format:H:i', 'after_or_equal:18:00', 'before_or_equal:23:59'],
            'wake_up_time' => ['required', 'date_format:H:i', 'after_or_equal:04:00', 'before_or_equal:11:00'],
            'gap_threshold_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'flying_quotes_enabled' => ['sometimes', 'boolean'],
        ]);

        // HTML checkboxes don't post when unchecked, so an absent value
        // means the user disabled the toggle.
        $data['flying_quotes_enabled'] = $request->boolean('flying_quotes_enabled');

        $user->forceFill($data)->save();

        return redirect()
            ->route('settings.show')
            ->with('status', 'settings-updated');
    }

    public function destroyAccount(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'password' => ['required', 'string'],
            'confirm_text' => ['required', 'string'],
        ]);

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        if (mb_strtolower(trim($request->input('confirm_text'))) !== 'delete my account') {
            throw ValidationException::withMessages([
                'confirm_text' => 'Type the phrase exactly to confirm.',
            ]);
        }

        // Hard-delete: the User model uses SoftDeletes by default, but a
        // user-initiated "delete my account" should genuinely remove the row
        // (privacy / compliance) rather than just set deleted_at.
        Auth::guard('web')->logout();
        $user->forceDelete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('toast', 'Your account has been deleted.');
    }
}
