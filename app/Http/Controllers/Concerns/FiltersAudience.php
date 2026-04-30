<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Badge;
use App\Enums\UserRole;
use App\Models\User;

trait FiltersAudience
{
    private function resolveAudiences(User $user): array
    {
        $audiences = ['all'];

        if (in_array(Badge::Verified->value, $user->badges ?? [])) {
            $audiences[] = 'verified';
        }

        if (in_array($user->role, [UserRole::Critic->value, UserRole::Admin->value])) {
            $audiences[] = 'press';
        }

        return $audiences;
    }

    private function userMatchesAudience(User $user, string $audience): bool
    {
        return in_array($audience, $this->resolveAudiences($user));
    }
}
