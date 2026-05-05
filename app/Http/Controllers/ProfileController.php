<?php

namespace App\Http\Controllers;

use App\Rules\NotDisposableEmail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return view('profile.index', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
                new NotDisposableEmail(),
            ],
        ]);

        $emailChanged = $data['email'] !== $user->email;

        $user->forceFill($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged && method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()
            ->route('profile.show')
            ->with('status', $emailChanged ? 'profile-updated-email-changed' : 'profile-updated');
    }
}
