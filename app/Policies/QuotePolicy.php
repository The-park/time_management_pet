<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    /**
     * Only the owner of a user-created quote may view it through the
     * /quotes management screen. Admin quotes (user_id NULL) are NEVER
     * editable from this controller — they're managed in /admin/quotes.
     */
    public function view(User $user, Quote $quote): bool
    {
        return $quote->user_id !== null && $user->id === $quote->user_id;
    }

    public function update(User $user, Quote $quote): bool
    {
        return $quote->user_id !== null
            && $user->id === $quote->user_id
            && $user->status === 'active';
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $quote->user_id !== null
            && $user->id === $quote->user_id
            && $user->status === 'active';
    }
}
