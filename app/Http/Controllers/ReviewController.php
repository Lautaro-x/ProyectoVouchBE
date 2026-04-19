<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewScore;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
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
            return response()->json(['message' => 'Already reviewed.'], 422);
        }

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

        $this->scoring->recalculateProductScores($product, $user->role);

        return response()->json($review->fresh(), 201);
    }

    public function editForm(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id) {
            abort(403);
        }

        $product = $review->product->load(['genres.categories']);

        return response()->json([
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

    public function update(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'body'                 => 'nullable|string|max:2000',
            'scores'               => 'required|array|min:1',
            'scores.*.category_id' => 'required|exists:Categories,id',
            'scores.*.score'       => 'required|integer|min:0|max:10',
        ]);

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

        $this->scoring->recalculateProductScores($review->product, $review->user->role);

        return response()->json($review->fresh());
    }
}
