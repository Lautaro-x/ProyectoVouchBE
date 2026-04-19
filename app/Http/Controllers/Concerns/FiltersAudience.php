<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;

trait FiltersAudience
{
    private function userMatchesAudience(User $user, string $audience): bool
    {
        return match ($audience) {
            'verified' => in_array('verificado', $user->badges ?? []),
            'press'    => in_array($user->role, ['critic', 'admin']),
            default    => true,
        };
    }
}
