<?php

namespace App\Services;

use App\Enums\Badge;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductScore;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ScoringService
{
    public function calculateWeightedScore(Review $review): float
    {
        $product = $review->product->load(['genres.categories']);

        $weightMap = [];
        foreach ($product->genres as $genre) {
            foreach ($genre->categories as $category) {
                $weight = (float) $category->pivot->weight;
                if (!isset($weightMap[$category->id]) || $weight > $weightMap[$category->id]) {
                    $weightMap[$category->id] = $weight;
                }
            }
        }

        arsort($weightMap);
        $weightMap = array_slice($weightMap, 0, 15, true);

        $scores      = $review->scores->keyBy('category_id');
        $numerator   = 0.0;
        $denominator = 0.0;

        foreach ($weightMap as $categoryId => $weight) {
            $score        = (float) ($scores->get($categoryId)?->score ?? 0);
            $numerator   += $score * $weight;
            $denominator += $weight;
        }

        if ($denominator <= 0) {
            return 0.0;
        }

        $raw = round($numerator / $denominator, 10);

        return floor($raw * 10) / 10;
    }

    public function calculateLetterGrade(float $score): string
    {
        if ($score >= 10.0) return 'S';

        $integer    = (int) floor($score);
        $hasDecimal = $score > $integer;

        return match(true) {
            $integer >= 9 => $hasDecimal ? 'A+' : 'A',
            $integer >= 8 => $hasDecimal ? 'B+' : 'B',
            $integer >= 7 => $hasDecimal ? 'C+' : 'C',
            $integer >= 6 => $hasDecimal ? 'D+' : 'D',
            $integer >= 5 => $hasDecimal ? 'E+' : 'E',
            default       => 'F',
        };
    }

    public function recalculateProductScores(Product $product, ?string $role = null): void
    {
        $updates = ['updated_at' => now()];

        if ($role === null || $role === UserRole::User->value) {
            $updates['global_score'] = $this->averageByRole($product, UserRole::User->value);
        }

        if ($role === null || $role === UserRole::Critic->value) {
            $updates['pro_score'] = $this->averageByRole($product, UserRole::Critic->value);
        }

        if (count($updates) > 1) {
            ProductScore::updateOrCreate(['product_id' => $product->id], $updates);
            Cache::forget('relevant_products');
        }
    }

    public function calculateTrustScore(Product $product, User $user): ?float
    {
        return $this->trustScoreFromIds(
            $product,
            $user->following()->pluck('followed_id')->toArray()
        );
    }

    public function followerScore(Product $product, User $user): ?float
    {
        if (!in_array(Badge::Verified->value, $user->badges ?? [])) return null;
        if (!$user->consent_follower_score) return null;

        return $this->trustScoreFromIds(
            $product,
            $user->followers()->pluck('follower_id')->toArray()
        );
    }

    public function trustScoreFromIds(Product $product, array $followedIds): ?float
    {
        if (empty($followedIds)) {
            return null;
        }

        $scores = $product->reviews()
            ->whereIn('user_id', $followedIds)
            ->whereNull('banned_at')
            ->pluck('weighted_score');

        if ($scores->isEmpty()) {
            return null;
        }

        return floor($scores->avg() * 10) / 10;
    }

    private function averageByRole(Product $product, string $role): ?float
    {
        $avg = $product->reviews()
            ->join('users', 'users.id', '=', 'reviews.user_id')
            ->whereNull('reviews.banned_at')
            ->where('users.role', $role)
            ->avg('reviews.weighted_score');

        return $avg !== null ? floor((float) $avg * 10) / 10 : null;
    }
}
