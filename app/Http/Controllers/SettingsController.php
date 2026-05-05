<?php

namespace App\Http\Controllers;

use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function show(Request $request)
    {
        return view('settings', [
            'user' => $request->user(),
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
        ]);

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
