<?php

namespace App\Services;

use App\Enums\Badge;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class BadgeService
{
    const REVIEW_MILESTONES = [
        10  => Badge::NoviceCritic,
        20  => Badge::JuniorCritic,
        50  => Badge::SeniorCritic,
        100 => Badge::MasterCritic,
        200 => Badge::TheCritic,
    ];

    const FOLLOWER_MILESTONES = [
        10   => Badge::FriendCritic,
        100  => Badge::SoughtCritic,
        1000 => Badge::ReliableCritic,
        3000 => Badge::FamousCritic,
        6000 => Badge::InfluentialCritic,
    ];

    public function award(User $user, string $badge): void
    {
        $badges = $user->badges ?? [];
        if (!in_array($badge, $badges, true)) {
            $user->badges = [...$badges, $badge];
            $user->save();
            Cache::forget("badge_progress_{$user->id}");
        }
    }

    public function revoke(User $user, string $badge): void
    {
        $user->badges = array_values(
            array_filter($user->badges ?? [], fn($b) => $b !== $badge)
        );
        $user->save();
        Cache::forget("badge_progress_{$user->id}");
    }

    public function getProgress(User $user): array
    {
        return Cache::remember("badge_progress_{$user->id}", 300, function () use ($user) {
            return $this->buildProgress($user);
        });
    }

    private function buildProgress(User $user): array
    {
        $awarded   = $user->badges ?? [];
        $reviews   = $user->reviews()->whereNull('banned_at')->count();
        $followers = $user->followers()->count();

        $result = [];

        foreach (self::REVIEW_MILESTONES as $threshold => $badge) {
            $result[$badge->value] = [
                'current'   => min($reviews, $threshold),
                'threshold' => $threshold,
                'awarded'   => in_array($badge->value, $awarded, true),
                'claimable' => $reviews >= $threshold && !in_array($badge->value, $awarded, true),
            ];
        }

        foreach (self::FOLLOWER_MILESTONES as $threshold => $badge) {
            $result[$badge->value] = [
                'current'   => min($followers, $threshold),
                'threshold' => $threshold,
                'awarded'   => in_array($badge->value, $awarded, true),
                'claimable' => $followers >= $threshold && !in_array($badge->value, $awarded, true),
            ];
        }

        $fastBadge       = Badge::FastCritic;
        $isFirstReviewer = Review::where('user_id', $user->id)
            ->whereNull('banned_at')
            ->whereNotExists(function ($q) use ($user) {
                $q->from('reviews as r2')
                  ->whereColumn('r2.product_id', 'reviews.product_id')
                  ->where('r2.user_id', '!=', $user->id)
                  ->whereNull('r2.banned_at')
                  ->whereColumn('r2.created_at', '<', 'reviews.created_at');
            })
            ->exists();

        $result[$fastBadge->value] = [
            'current'   => $isFirstReviewer ? 1 : 0,
            'threshold' => 1,
            'awarded'   => in_array($fastBadge->value, $awarded, true),
            'claimable' => $isFirstReviewer && !in_array($fastBadge->value, $awarded, true),
        ];

        return $result;
    }

    public function claim(User $user, string $badge): bool
    {
        if (in_array($badge, $user->badges ?? [], true)) {
            return false;
        }

        $progress = $this->getProgress($user);

        if (!isset($progress[$badge]) || !$progress[$badge]['claimable']) {
            return false;
        }

        $this->award($user, $badge);
        return true;
    }
}
