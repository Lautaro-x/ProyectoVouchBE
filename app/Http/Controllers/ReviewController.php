<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewScore;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    use ApiResponse;

    public function __construct(private ScoringService $scoring) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'           => 'required|exists:Products,id',
            'body'                 => 'nullable|string|max:2000',
            'scores'               => 'required|array|min:1',
            'scores.*.category_id' => 'required|exists:Categories,id',
            'scores.*.score'       => 'required|integer|min:0|max:10',
        ]);

        $user    = $request->user();
        $product = Product::findOrFail($data['product_id']);

        if ($user->reviews()->where('product_id', $product->id)->exists()) {
            return $this->error('Already reviewed.');
        }

        $review = DB::transaction(function () use ($data, $user, $product) {
            $review = Review::create([
                'user_id'        => $user->id,
                'product_id'     => $product->id,
                'body'           => $data['body'] ?? null,
                'weighted_score' => 0,
                'letter_grade'   => 'F',
            ]);

            foreach ($data['scores'] as $s) {
                ReviewScore::create([
                    'review_id'   => $review->id,
                    'category_id' => $s['category_id'],
                    'score'       => $s['score'],
                ]);
            }

            $review->load('scores');
            $weightedScore = $this->scoring->calculateWeightedScore($review);
            $review->update([
                'weighted_score' => $weightedScore,
                'letter_grade'   => $this->scoring->calculateLetterGrade($weightedScore),
            ]);

            return $review;
        });

        $this->scoring->recalculateProductScores($product, $user->role);

        return $this->created($review->fresh());
    }

    public function editForm(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id) {
            return $this->forbidden();
        }

        $product = $review->product->load(['genres.categories']);

        return $this->ok([
            'id'          => $product->id,
            'title'       => $product->title,
            'cover_image' => $product->cover_image,
            'type'        => $product->type,
            'slug'        => $product->slug,
            'categories'  => $product->uniqueCategories(),
            'body'        => $review->body,
            'scores'      => $review->scores->pluck('score', 'category_id'),
        ]);
    }

    public function shareData(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id) {
            return $this->forbidden();
        }

        $review->load(['scores.category', 'user', 'product']);

        return $this->ok([
            'review' => [
                'id'             => $review->id,
                'body'           => $review->body,
                'letter_grade'   => $review->letter_grade,
                'weighted_score' => $review->weighted_score,
            ],
            'product' => [
                'title'       => $review->product->title,
                'cover_image' => $review->product->cover_image,
            ],
            'user' => [
                'name'   => $review->user->name,
                'avatar' => $review->user->avatar,
            ],
            'scores' => $review->scores->map(fn($s) => [
                'category_id' => $s->category_id,
                'name'        => $s->category->getTranslations('name'),
                'score'       => $s->score,
            ]),
        ]);
    }

    public function update(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id) {
            return $this->forbidden();
        }

        $data = $request->validate([
            'body'                 => 'nullable|string|max:2000',
            'scores'               => 'required|array|min:1',
            'scores.*.category_id' => 'required|exists:Categories,id',
            'scores.*.score'       => 'required|integer|min:0|max:10',
        ]);

        DB::transaction(function () use ($data, $review) {
            $review->update(['body' => $data['body'] ?? null]);

            foreach ($data['scores'] as $s) {
                ReviewScore::where('review_id', $review->id)
                    ->where('category_id', $s['category_id'])
                    ->update(['score' => $s['score']]);
            }

            $review->load('scores');
            $weightedScore = $this->scoring->calculateWeightedScore($review);
            $review->update([
                'weighted_score' => $weightedScore,
                'letter_grade'   => $this->scoring->calculateLetterGrade($weightedScore),
            ]);
        });

        $this->scoring->recalculateProductScores($review->product, $review->user->role);

        return $this->ok($review->fresh());
    }
}
