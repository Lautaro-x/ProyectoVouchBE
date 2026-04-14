<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private ScoringService $scoring) {}

    public function index(Request $request): JsonResponse
    {
        $reviews = Review::with(['user', 'product'])
            ->when($request->banned, fn($q) => $q->whereNotNull('banned_at'))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($reviews);
    }

    public function ban(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate([
            'ban_reason' => 'nullable|string|max:255',
        ]);

        $review->update([
            'banned_at'  => now(),
            'ban_reason' => $data['ban_reason'] ?? null,
        ]);

        $this->scoring->recalculateProductScores($review->product);

        return response()->json($review);
    }

    public function unban(Review $review): JsonResponse
    {
        $review->update(['banned_at' => null, 'ban_reason' => null]);

        $this->scoring->recalculateProductScores($review->product);

        return response()->json($review);
    }
}
