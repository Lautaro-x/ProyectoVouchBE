<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserReviewController extends Controller
{
    public function games(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'page'   => 'nullable|integer|min:1',
        ]);

        $reviews = $request->user()
            ->reviews()
            ->whereHas('product', fn($q) => $q->where('type', 'game'))
            ->with(['product:id,type,title,slug,cover_image'])
            ->when($request->search, function ($q, $search) {
                $q->whereHas('product', fn($q2) => $q2->where('title', 'like', "%{$search}%"));
            })
            ->orderByDesc('created_at')
            ->paginate(24);

        return response()->json([
            'data'         => $reviews->map(fn($r) => [
                'id'             => $r->id,
                'weighted_score' => $r->weighted_score,
                'letter_grade'   => $r->letter_grade,
                'created_at'     => $r->created_at,
                'product'        => [
                    'id'          => $r->product->id,
                    'title'       => $r->product->title,
                    'slug'        => $r->product->slug,
                    'type'        => $r->product->type,
                    'cover_image' => $r->product->cover_image,
                ],
            ]),
            'current_page' => $reviews->currentPage(),
            'last_page'    => $reviews->lastPage(),
            'total'        => $reviews->total(),
        ]);
    }
}
