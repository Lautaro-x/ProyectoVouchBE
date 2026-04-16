<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductReviewController extends Controller
{
    public function index(Product $product): JsonResponse
    {
        $reviews = $product->reviews()
            ->with('user:id,name,avatar')
            ->whereNull('banned_at')
            ->whereHas('user', fn($q) => $q->where('reviews_public', true)->whereNull('banned_at'))
            ->orderByDesc('created_at')
            ->paginate(6);

        return response()->json([
            'data' => $reviews->map(fn($r) => [
                'id'             => $r->id,
                'user'           => $r->user->only(['id', 'name', 'avatar']),
                'letter_grade'   => $r->letter_grade,
                'weighted_score' => $r->weighted_score,
                'body'           => $r->body,
                'created_at'     => $r->created_at,
            ]),
            'current_page' => $reviews->currentPage(),
            'last_page'    => $reviews->lastPage(),
            'total'        => $reviews->total(),
        ]);
    }
}
