<?php

namespace App\Policies;

use App\Models\TimeBlock;
use App\Models\User;

class TimeBlockPolicy
{
    public function view(User $user, TimeBlock $timeBlock): bool
    {
        return $user->id === $timeBlock->user_id;
    }

    public function create(User $user): bool
    {
        return $user->status === 'active';
    }

    public function update(User $user, TimeBlock $timeBlock): bool
    {
        if ($user->id !== $timeBlock->user_id) {
            return false;
        }

        return $this->isEditableToday($user, $timeBlock);
    }

    public function delete(User $user, TimeBlock $timeBlock): bool
    {
        if ($user->id !== $timeBlock->user_id) {
            return false;
        }

        return $this->isEditableToday($user, $timeBlock);
    }

    protected function isEditableToday(User $user, TimeBlock $timeBlock): bool
    {
        $timezone = $user->timezone ?: 'UTC';

        return $timeBlock->start_time
            ->copy()
            ->setTimezone($timezone)
            ->isSameDay(now($timezone));
    }
}
