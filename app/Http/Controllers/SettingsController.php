<?php

namespace App\Http\Controllers;

use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
}
