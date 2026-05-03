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
            'sort'   => 'nullable|string|in:date_desc,date_asc,score_desc,score_asc,title_asc',
            'grade'  => 'nullable|string|max:3',
        ]);

        $query = $request->user()
            ->reviews()
            ->whereHas('product', fn($q) => $q->where('type', 'game'))
            ->with(['product:id,type,title,slug,cover_image'])
            ->when($request->filled('search'), fn($q) =>
                $q->whereHas('product', fn($q2) => $q2->where('title', 'like', "%{$request->input('search')}%"))
            )
            ->when($request->filled('grade'), fn($q) =>
                $q->where('letter_grade', $request->input('grade'))
            );

        match ($request->input('sort', 'date_desc')) {
            'date_asc'   => $query->orderBy('reviews.created_at'),
            'score_desc' => $query->orderByDesc('reviews.weighted_score'),
            'score_asc'  => $query->orderBy('reviews.weighted_score'),
            'title_asc'  => $query->join('Products', 'reviews.product_id', '=', 'Products.id')
                                   ->select('reviews.*')
                                   ->orderBy('Products.title'),
            default      => $query->orderByDesc('reviews.created_at'),
        };

        $reviews = $query->paginate(24);

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
