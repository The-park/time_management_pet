<?php

namespace App\Policies;

use App\Models\Countdown;
use App\Models\User;

class CountdownPolicy
{
    public function view(User $user, Countdown $countdown): bool
    {
        return $user->id === $countdown->user_id;
    }

    public function update(User $user, Countdown $countdown): bool
    {
        return $user->id === $countdown->user_id && $user->status === 'active';
    }
}
