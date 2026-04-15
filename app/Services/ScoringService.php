<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductScore;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;

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

        return floor(($numerator / $denominator) * 10) / 10;
    }

    public function calculateLetterGrade(float $score): string
    {
        if ($score >= 10.0) return 'S';

        $integer    = (int) floor($score);
        $hasDecimal = ($score - $integer) >= 0.1;

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
        $reviews = $product->reviews()->with('user')->whereNull('banned_at')->get();
        $updates = ['updated_at' => now()];

        if ($role === null || $role === 'user') {
            $updates['global_score'] = $this->average(
                $reviews->filter(fn(Review $r) => $r->user->role === 'user')
            );
        }

        if ($role === null || $role === 'critic') {
            $updates['pro_score'] = $this->average(
                $reviews->filter(fn(Review $r) => $r->user->role === 'critic')
            );
        }

        if (count($updates) > 1) {
            ProductScore::updateOrCreate(['product_id' => $product->id], $updates);
        }
    }

    public function calculateTrustScore(Product $product, User $user): ?float
    {
        $followedIds = $user->following()->pluck('followed_id');

        if ($followedIds->isEmpty()) {
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

    private function average(Collection $reviews): ?float
    {
        if ($reviews->isEmpty()) {
            return null;
        }

        return floor($reviews->avg('weighted_score') * 10) / 10;
    }
}
