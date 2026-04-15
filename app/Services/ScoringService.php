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
        $numerator   = 0;
        $denominator = 0;

        foreach ($weightMap as $categoryId => $weight) {
            $score        = $scores->get($categoryId)?->score ?? 0;
            $numerator   += $score * $weight;
            $denominator += $weight;
        }

        return $denominator > 0 ? (int) round(($numerator / $denominator) * 10) : 0;
    }

    public function calculateLetterGrade(int $score): string
    {
        if ($score === 100) return 'S';

        foreach (self::LETTER_GRADES as $threshold => $grade) {
            if ($score >= $threshold) {
                return $grade;
            }
        }

        return 'F';
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

    public function calculateTrustScore(Product $product, User $user): ?int
    {
        $followedIds = $user->following()->pluck('followed_id');

        if ($followedIds->isEmpty()) {
            return null;
        }

        $scores = $product->reviews()
            ->whereIn('user_id', $followedIds)
            ->whereNull('banned_at')
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
