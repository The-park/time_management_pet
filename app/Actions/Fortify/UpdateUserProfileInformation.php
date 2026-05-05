<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\NotDisposableEmail;
use DateTimeZone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
                new NotDisposableEmail(),
            ],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'end_of_day_time' => ['required', 'date_format:H:i', 'after_or_equal:18:00', 'before_or_equal:23:59'],
            'wake_up_time' => ['required', 'date_format:H:i', 'after_or_equal:04:00', 'before_or_equal:11:00'],
            'gap_threshold_minutes' => ['required', 'integer', 'min:15', 'max:240'],
        ])->validate();

        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'timezone' => $input['timezone'],
            'end_of_day_time' => $input['end_of_day_time'],
            'wake_up_time' => $input['wake_up_time'],
            'gap_threshold_minutes' => $input['gap_threshold_minutes'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
            $user->save();

            $user->sendEmailVerificationNotification();

            return;
        }

        $user->save();
    }
}
