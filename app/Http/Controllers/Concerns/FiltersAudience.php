<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Badge;
use App\Enums\UserRole;
use App\Models\User;

trait FiltersAudience
{
    private function userMatchesAudience(User $user, string $audience): bool
    {
        return match ($audience) {
            'verified' => in_array(Badge::Verified->value, $user->badges ?? []),
            'press'    => in_array($user->role, [UserRole::Critic->value, UserRole::Admin->value]),
            default    => true,
        };
    }
}
