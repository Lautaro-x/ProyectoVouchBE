<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Genre;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeProductWithCategory(): array
    {
        $product  = Product::factory()->create();
        $genre    = Genre::factory()->create();
        $category = Category::factory()->create();

        $product->genres()->attach($genre->id);
        $genre->categories()->attach($category->id, ['weight' => 1.0]);

        return [$product, $category];
    }

    public function test_unauthenticated_user_cannot_create_review(): void
    {
        $this->postJson('/api/reviews', [])->assertStatus(401);
    }

    public function test_banned_user_cannot_create_review(): void
    {
        $user = User::factory()->create(['banned_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reviews', [])
            ->assertStatus(403);
    }

    public function test_authenticated_user_can_create_review(): void
    {
        [$product, $category] = $this->makeProductWithCategory();
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/reviews', [
            'product_id' => $product->id,
            'body'       => 'Great game!',
            'scores'     => [
                ['category_id' => $category->id, 'score' => 8],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('Reviews', [
            'user_id'    => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_cannot_review_same_product_twice(): void
    {
        [$product, $category] = $this->makeProductWithCategory();
        $user = User::factory()->create();

        Review::create([
            'user_id'        => $user->id,
            'product_id'     => $product->id,
            'body'           => null,
            'weighted_score' => 50,
            'letter_grade'   => 'E',
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/reviews', [
            'product_id' => $product->id,
            'body'       => 'Second attempt',
            'scores'     => [
                ['category_id' => $category->id, 'score' => 7],
            ],
        ])->assertStatus(422);
    }

    public function test_review_creates_correct_weighted_score(): void
    {
        [$product, $category] = $this->makeProductWithCategory();
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'sanctum')->postJson('/api/reviews', [
            'product_id' => $product->id,
            'scores'     => [
                ['category_id' => $category->id, 'score' => 7],
            ],
        ])->assertStatus(201);

        // Single category weight=1.0, score=7 → weighted_score = 7.0, grade = C
        $this->assertDatabaseHas('Reviews', [
            'user_id'        => $user->id,
            'product_id'     => $product->id,
            'weighted_score' => 7.0,
            'letter_grade'   => 'C',
        ]);
    }

    public function test_edit_form_requires_authentication(): void
    {
        $review = $this->createReviewForNewUser();

        $this->getJson("/api/reviews/{$review->id}/edit-form")->assertStatus(401);
    }

    public function test_edit_form_forbidden_for_other_user(): void
    {
        $review = $this->createReviewForNewUser();
        $other  = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/reviews/{$review->id}/edit-form")
            ->assertStatus(403);
    }

    public function test_edit_form_accessible_by_owner(): void
    {
        [$product, $category] = $this->makeProductWithCategory();
        $user   = User::factory()->create();
        $review = Review::create([
            'user_id'        => $user->id,
            'product_id'     => $product->id,
            'body'           => 'My review',
            'weighted_score' => 70,
            'letter_grade'   => 'C',
        ]);
        ReviewScore::create(['review_id' => $review->id, 'category_id' => $category->id, 'score' => 7]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/reviews/{$review->id}/edit-form")
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $product->id]);
    }

    public function test_update_review_forbidden_for_other_user(): void
    {
        [$product, $category] = $this->makeProductWithCategory();
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $review = Review::create([
            'user_id'        => $owner->id,
            'product_id'     => $product->id,
            'body'           => null,
            'weighted_score' => 50,
            'letter_grade'   => 'E',
        ]);

        $this->actingAs($other, 'sanctum')
            ->putJson("/api/reviews/{$review->id}", [
                'scores' => [['category_id' => $category->id, 'score' => 5]],
            ])
            ->assertStatus(403);
    }

    public function test_owner_can_update_review(): void
    {
        [$product, $category] = $this->makeProductWithCategory();
        $user = User::factory()->create(['role' => 'user']);

        $review = Review::create([
            'user_id'        => $user->id,
            'product_id'     => $product->id,
            'body'           => null,
            'weighted_score' => 50,
            'letter_grade'   => 'E',
        ]);
        ReviewScore::create(['review_id' => $review->id, 'category_id' => $category->id, 'score' => 5]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/reviews/{$review->id}", [
                'body'   => 'Updated body',
                'scores' => [['category_id' => $category->id, 'score' => 9]],
            ])
            ->assertStatus(200);

        // score=9, weight=1.0 → weighted_score = 9.0, grade = A
        $this->assertDatabaseHas('Reviews', [
            'id'             => $review->id,
            'weighted_score' => 9.0,
            'letter_grade'   => 'A',
        ]);
    }

    private function createReviewForNewUser(): Review
    {
        [$product] = $this->makeProductWithCategory();
        $user = User::factory()->create();

        return Review::create([
            'user_id'        => $user->id,
            'product_id'     => $product->id,
            'body'           => null,
            'weighted_score' => 50,
            'letter_grade'   => 'E',
        ]);
    }
}
