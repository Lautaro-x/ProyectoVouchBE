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
        $allowed = ['id', 'weighted_score', 'created_at'];
        $sortBy  = in_array($request->sort_by, $allowed) ? $request->sort_by : 'id';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';
        $perPage = min((int) $request->get('per_page', 25), 100);

        $reviews = Review::with(['user', 'product'])
            ->when($request->banned, fn($q) => $q->whereNotNull('banned_at'))
            ->when($request->search, fn($q) => $q->where(function ($inner) use ($request) {
                $inner->whereHas('user', fn($u) => $u->where('name', 'like', "%{$request->search}%"))
                      ->orWhereHas('product', fn($p) => $p->where('title', 'like', "%{$request->search}%"));
            }))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);

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
