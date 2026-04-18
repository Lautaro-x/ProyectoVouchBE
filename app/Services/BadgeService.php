<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;

class BadgeService
{
    const REVIEW_MILESTONES = [
        10  => 'critico-novel',
        20  => 'critico-junior',
        50  => 'critico-senior',
        100 => 'critico-maestro',
        200 => 'el-critico',
    ];

    const FOLLOWER_MILESTONES = [
        10   => 'critico-amigo',
        100  => 'critico-solicitado',
        1000 => 'critico-fiable',
        3000 => 'critico-famoso',
        6000 => 'critico-influyente',
    ];

    public function award(User $user, string $badge): void
    {
        $badges = $user->badges ?? [];
        if (!in_array($badge, $badges, true)) {
            $user->badges = [...$badges, $badge];
            $user->save();
        }
    }

    public function revoke(User $user, string $badge): void
    {
        $user->badges = array_values(
            array_filter($user->badges ?? [], fn($b) => $b !== $badge)
        );
        $user->save();
    }

    public function getProgress(User $user): array
    {
        $awarded   = $user->badges ?? [];
        $reviews   = $user->reviews()->whereNull('banned_at')->count();
        $followers = $user->followers()->count();

        $result = [];

        foreach (self::REVIEW_MILESTONES as $threshold => $badge) {
            $result[$badge] = [
                'current'   => min($reviews, $threshold),
                'threshold' => $threshold,
                'awarded'   => in_array($badge, $awarded, true),
                'claimable' => $reviews >= $threshold && !in_array($badge, $awarded, true),
            ];
        }

        foreach (self::FOLLOWER_MILESTONES as $threshold => $badge) {
            $result[$badge] = [
                'current'   => min($followers, $threshold),
                'threshold' => $threshold,
                'awarded'   => in_array($badge, $awarded, true),
                'claimable' => $followers >= $threshold && !in_array($badge, $awarded, true),
            ];
        }

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

        $result['critico-rapido'] = [
            'current'   => $isFirstReviewer ? 1 : 0,
            'threshold' => 1,
            'awarded'   => in_array('critico-rapido', $awarded, true),
            'claimable' => $isFirstReviewer && !in_array('critico-rapido', $awarded, true),
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
