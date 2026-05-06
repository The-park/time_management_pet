<?php

namespace App\Policies;

use App\Models\Goal;
use App\Models\User;

class GoalPolicy
{
    public function view(User $user, Goal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    public function update(User $user, Goal $goal): bool
    {
        return $user->id === $goal->user_id && $user->status === 'active';
    }

    public function delete(User $user, Goal $goal): bool
    {
        return $user->id === $goal->user_id && $user->status === 'active';
    }
}
