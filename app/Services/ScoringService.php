<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductScore;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;

class ScoringService
{
    private const LETTER_GRADES = [
        97 => 'A+',
        93 => 'A',
        90 => 'A-',
        87 => 'B+',
        83 => 'B',
        80 => 'B-',
        77 => 'C+',
        73 => 'C',
        70 => 'C-',
        67 => 'D+',
        63 => 'D',
        60 => 'D-',
        0  => 'F',
    ];

    public function calculateWeightedScore(Review $review): int
    {
        $categories = $review->product->genre->categories;
        $scores     = $review->scores->keyBy('category_id');

        $numerator   = 0;
        $denominator = 0;

        foreach ($categories as $category) {
            $weight = (float) $category->pivot->weight;
            $score  = $scores->get($category->id)?->score ?? 0;

            $numerator   += $score * $weight;
            $denominator += $weight;
        }

        if ($denominator === 0) {
            return 0;
        }

        return (int) round(($numerator / $denominator) * 10);
    }

    public function calculateLetterGrade(int $score): string
    {
        foreach (self::LETTER_GRADES as $threshold => $grade) {
            if ($score >= $threshold) {
                return $grade;
            }
        }

        return 'F';
    }

    public function recalculateProductScores(Product $product): void
    {
        $allReviews = $product->reviews()->with('user')->get();
        $proReviews = $allReviews->filter(fn(Review $r) => in_array($r->user->role, ['critic', 'admin']));

        ProductScore::updateOrCreate(
            ['product_id' => $product->id],
            [
                'global_score' => $this->average($allReviews),
                'pro_score'    => $this->average($proReviews),
                'updated_at'   => now(),
            ]
        );
    }

    public function calculateTrustScore(Product $product, User $user): ?int
    {
        $followedIds = $user->following()->pluck('followed_id');

        if ($followedIds->isEmpty()) {
            return null;
        }

        $scores = $product->reviews()
            ->whereIn('user_id', $followedIds)
            ->pluck('weighted_score');

        return $scores->isNotEmpty() ? (int) round($scores->avg()) : null;
    }

    private function average(Collection $reviews): ?int
    {
        if ($reviews->isEmpty()) {
            return null;
        }

        return (int) round($reviews->avg('weighted_score'));
    }
}
