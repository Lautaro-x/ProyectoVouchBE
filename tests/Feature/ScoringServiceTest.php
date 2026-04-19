<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Genre;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewScore;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private ScoringService $scoring;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoring = new ScoringService();
    }

    private function makeProductWithCategories(array $weightsByGenre): array
    {
        $product    = Product::factory()->create();
        $categories = [];

        foreach ($weightsByGenre as $genreWeights) {
            $genre = Genre::factory()->create();
            $product->genres()->attach($genre->id);

            foreach ($genreWeights as $catKey => $weight) {
                if (!isset($categories[$catKey])) {
                    $categories[$catKey] = Category::factory()->create();
                }
                $genre->categories()->attach($categories[$catKey]->id, ['weight' => $weight]);
            }
        }

        return [$product, $categories];
    }

    private function makeReview(Product $product, User $user, array $scoresByCatKey, array $categories): Review
    {
        $review = Review::create([
            'user_id'        => $user->id,
            'product_id'     => $product->id,
            'body'           => null,
            'weighted_score' => 0,
            'letter_grade'   => 'F',
        ]);

        foreach ($scoresByCatKey as $catKey => $score) {
            ReviewScore::create([
                'review_id'   => $review->id,
                'category_id' => $categories[$catKey]->id,
                'score'       => $score,
            ]);
        }

        return $review;
    }

    public function test_basic_weighted_average(): void
    {
        // C1 weight=0.6, C2 weight=0.4
        // Scores: C1=8, C2=6
        // Expected: (8×0.6 + 6×0.4) / 1.0 = 7.2
        [$product, $cats] = $this->makeProductWithCategories([
            [0 => 0.6, 1 => 0.4],
        ]);

        $user   = User::factory()->create();
        $review = $this->makeReview($product, $user, [0 => 8, 1 => 6], $cats);

        $this->assertSame(7.2, $this->scoring->calculateWeightedScore($review));
    }

    public function test_variable_weights_change_the_result(): void
    {
        // Same scores (C1=8, C2=6) but heavier weight on C1
        // C1 weight=0.9, C2 weight=0.1
        // Expected: (8×0.9 + 6×0.1) / 1.0 = 7.8
        [$product, $cats] = $this->makeProductWithCategories([
            [0 => 0.9, 1 => 0.1],
        ]);

        $user   = User::factory()->create();
        $review = $this->makeReview($product, $user, [0 => 8, 1 => 6], $cats);

        $result = $this->scoring->calculateWeightedScore($review);

        $this->assertSame(7.8, $result);
        $this->assertNotSame(7.2, $result, 'Different weights must produce different results');
    }

    public function test_max_weight_algorithm_across_multiple_genres(): void
    {
        // Genre G1: C1(0.3), C2(0.7)
        // Genre G2: C1(0.6), C3(0.4)
        // MAX weights: C1=0.6, C2=0.7, C3=0.4
        // Scores: C1=8, C2=6, C3=9
        // Sorted by weight desc: C2(0.7), C1(0.6), C3(0.4)
        // Weighted: (6×0.7 + 8×0.6 + 9×0.4) / 1.7 = 12.6/1.7 = 7.411... → 7.4
        [$product, $cats] = $this->makeProductWithCategories([
            [0 => 0.3, 1 => 0.7],
            [0 => 0.6, 2 => 0.4],
        ]);

        $user   = User::factory()->create();
        $review = $this->makeReview($product, $user, [0 => 8, 1 => 6, 2 => 9], $cats);

        $this->assertSame(7.4, $this->scoring->calculateWeightedScore($review));
    }

    public function test_max_weight_takes_highest_not_first(): void
    {
        // C1 appears in both genres: G1 gives 0.3, G2 gives 0.8
        // MAX should be 0.8
        // With only C1 (weight=0.8, score=5): result = 5.0
        [$product, $cats] = $this->makeProductWithCategories([
            [0 => 0.3],
            [0 => 0.8],
        ]);

        $user   = User::factory()->create();
        $review = $this->makeReview($product, $user, [0 => 5], $cats);

        $this->assertSame(5.0, $this->scoring->calculateWeightedScore($review));
    }

    public function test_missing_score_for_category_defaults_to_zero(): void
    {
        // C1 weight=0.5, C2 weight=0.5
        // Review only scores C1=8, C2 has no score (defaults to 0)
        // Expected: (8×0.5 + 0×0.5) / 1.0 = 4.0
        [$product, $cats] = $this->makeProductWithCategories([
            [0 => 0.5, 1 => 0.5],
        ]);

        $user   = User::factory()->create();
        $review = $this->makeReview($product, $user, [0 => 8], $cats);

        $this->assertSame(4.0, $this->scoring->calculateWeightedScore($review));
    }

    public function test_top_15_categories_truncation(): void
    {
        // 16 categories: first 15 have weight=0.1 (score=0), category 16 has weight=0.05 (score=10)
        // After arsort + slice(15): category 16 is excluded
        // Expected: (0×0.1 × 15) / 1.5 = 0.0
        $weights = [];
        for ($i = 0; $i < 15; $i++) {
            $weights[$i] = 0.1;
        }
        $weights[15] = 0.05;

        [$product, $cats] = $this->makeProductWithCategories([$weights]);

        $user   = User::factory()->create();
        $review = $this->makeReview($product, $user, [15 => 10], $cats);

        $this->assertSame(0.0, $this->scoring->calculateWeightedScore($review));
    }

    public function test_float_precision_exact_score_does_not_degrade(): void
    {
        // Single category weight=1.0, score=9 → exact 9.0
        // Without round() fix, floating point could yield 8.9999... → B+
        // With fix: should yield exactly 9.0 → letter grade A
        [$product, $cats] = $this->makeProductWithCategories([
            [0 => 1.0],
        ]);

        $user   = User::factory()->create();
        $review = $this->makeReview($product, $user, [0 => 9], $cats);

        $score = $this->scoring->calculateWeightedScore($review);

        $this->assertSame(9.0, $score);
        $this->assertSame('A', $this->scoring->calculateLetterGrade($score));
    }

    public function test_no_categories_returns_zero(): void
    {
        $product = Product::factory()->create();
        $user    = User::factory()->create();

        $review = Review::create([
            'user_id'        => $user->id,
            'product_id'     => $product->id,
            'body'           => null,
            'weighted_score' => 0,
            'letter_grade'   => 'F',
        ]);

        $this->assertSame(0.0, $this->scoring->calculateWeightedScore($review));
    }

    public function test_recalculate_product_scores_separates_user_and_critic(): void
    {
        [$product, $cats] = $this->makeProductWithCategories([
            [0 => 1.0],
        ]);

        $userReviewer   = User::factory()->create(['role' => 'user']);
        $criticReviewer = User::factory()->create(['role' => 'critic']);

        $userReview   = $this->makeReview($product, $userReviewer,   [0 => 6], $cats);
        $criticReview = $this->makeReview($product, $criticReviewer, [0 => 9], $cats);

        $userReview->update(['weighted_score'   => 60]);
        $criticReview->update(['weighted_score' => 90]);

        $this->scoring->recalculateProductScores($product);

        $score = $product->fresh()->score;

        $this->assertSame(60, $score->global_score);
        $this->assertSame(90, $score->pro_score);
    }

    public function test_recalculate_excludes_banned_reviews(): void
    {
        [$product, $cats] = $this->makeProductWithCategories([
            [0 => 1.0],
        ]);

        $u1 = User::factory()->create(['role' => 'user']);
        $u2 = User::factory()->create(['role' => 'user']);

        $r1 = $this->makeReview($product, $u1, [0 => 8], $cats);
        $r2 = $this->makeReview($product, $u2, [0 => 2], $cats);

        $r1->update(['weighted_score' => 80]);
        $r2->update(['weighted_score' => 20, 'banned_at' => now()]);

        $this->scoring->recalculateProductScores($product);

        // Only r1 counts (r2 banned), global_score = floor(80.0 * 10) / 10 = 80
        $this->assertSame(80, $product->fresh()->score->global_score);
    }

    public function test_trust_score_from_ids_returns_null_when_no_followed_ids(): void
    {
        $product = Product::factory()->create();

        $this->assertNull($this->scoring->trustScoreFromIds($product, []));
    }

    public function test_trust_score_from_ids_returns_null_when_no_matching_reviews(): void
    {
        $product = Product::factory()->create();

        $this->assertNull($this->scoring->trustScoreFromIds($product, [999, 998]));
    }

    public function test_trust_score_averages_followed_users_reviews(): void
    {
        $product = Product::factory()->create();
        $u1      = User::factory()->create();
        $u2      = User::factory()->create();

        Review::create([
            'user_id' => $u1->id, 'product_id' => $product->id,
            'body' => null, 'weighted_score' => 80, 'letter_grade' => 'B',
        ]);
        Review::create([
            'user_id' => $u2->id, 'product_id' => $product->id,
            'body' => null, 'weighted_score' => 60, 'letter_grade' => 'D',
        ]);

        // Average = 70, floor(70 * 10) / 10 = 70.0
        $result = $this->scoring->trustScoreFromIds($product, [$u1->id, $u2->id]);

        $this->assertSame(70.0, $result);
    }

    public function test_trust_score_excludes_banned_reviews(): void
    {
        $product = Product::factory()->create();
        $u1      = User::factory()->create();
        $u2      = User::factory()->create();

        Review::create([
            'user_id' => $u1->id, 'product_id' => $product->id,
            'body' => null, 'weighted_score' => 80, 'letter_grade' => 'B',
        ]);
        Review::create([
            'user_id' => $u2->id, 'product_id' => $product->id,
            'body' => null, 'weighted_score' => 20, 'letter_grade' => 'F',
            'banned_at' => now(),
        ]);

        // Only u1 counts → 80.0
        $result = $this->scoring->trustScoreFromIds($product, [$u1->id, $u2->id]);

        $this->assertSame(80.0, $result);
    }
}
