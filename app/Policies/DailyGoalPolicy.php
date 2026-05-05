<?php

namespace App\Policies;

use App\Models\DailyGoal;
use App\Models\User;

class DailyGoalPolicy
{
    public function view(User $user, DailyGoal $dailyGoal): bool
    {
        return $user->id === $dailyGoal->user_id;
    }

    public function update(User $user, DailyGoal $dailyGoal): bool
    {
        return $user->id === $dailyGoal->user_id && $user->status === 'active';
    }
}
