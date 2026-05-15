<?php

namespace App\Policies;

use App\Models\Rule;
use App\Models\User;

class RulePolicy
{
    public function view(User $user, Rule $rule): bool
    {
        return $user->id === $rule->user_id;
    }

    public function update(User $user, Rule $rule): bool
    {
        return $user->id === $rule->user_id && $user->status === 'active';
    }

    public function delete(User $user, Rule $rule): bool
    {
        return $user->id === $rule->user_id && $user->status === 'active';
    }
}
