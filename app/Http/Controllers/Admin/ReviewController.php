<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ParsesIndexRequest;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ParsesIndexRequest;

    public function __construct(private ScoringService $scoring) {}

    public function index(Request $request): JsonResponse
    {
        ['sortBy' => $sortBy, 'sortDir' => $sortDir, 'perPage' => $perPage] =
            $this->paginationParams($request, ['id', 'weighted_score', 'created_at']);

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
