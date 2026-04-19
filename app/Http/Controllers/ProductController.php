<?php

namespace App\Http\Controllers;

use App\Services\ScoringService;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ScoringService $scoring) {}

    public function games(Request $request): JsonResponse
    {
        $paginator = Product::with('score')
            ->addSelect([
                'Products.*',
                'latest_release' => \DB::table('Product_x_Platform')
                    ->selectRaw('MAX(release_date)')
                    ->whereColumn('product_id', 'Products.id'),
            ])
            ->where('type', 'game')
            ->when(
                $request->filled('search'),
                fn($q) => $q->where('Products.title', 'like', '%' . $request->search . '%')
            )
            ->orderByDesc('latest_release')
            ->paginate(12);

        $followedIds = [];
        if ($user = $request->user('sanctum')) {
            $followedIds = $user->following()->pluck('followed_id')->toArray();
        }

        return response()->json([
            'data'         => collect($paginator->items())->map(fn(Product $p) => array_merge(
                $this->formatCard($p),
                ['trust_grade' => !empty($followedIds)
                    ? (($ts = $this->scoring->trustScoreFromIds($p, $followedIds)) !== null
                        ? $this->scoring->calculateLetterGrade($ts)
                        : null)
                    : null,
                ]
            )),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'total'        => $paginator->total(),
        ]);
    }

    public function relevant(Request $request): JsonResponse
    {
        $products = Product::with(['score', 'platforms'])
            ->whereHas('score', fn($q) =>
                $q->whereRaw('GREATEST(COALESCE(global_score, 0), COALESCE(pro_score, 0)) >= 8.0')
            )
            ->get()
            ->sortByDesc(fn(Product $p) => $p->platforms->max('pivot.release_date') ?? '')
            ->take(6);

        $followedIds = [];
        if ($user = $request->user('sanctum')) {
            $followedIds = $user->following()->pluck('followed_id')->toArray();
        }

        return response()->json(
            $products->map(fn(Product $p) => array_merge(
                $this->formatCard($p),
                ['trust_grade' => !empty($followedIds)
                    ? (($ts = $this->scoring->trustScoreFromIds($p, $followedIds)) !== null
                        ? $this->scoring->calculateLetterGrade($ts)
                        : null)
                    : null,
                ]
            ))->values()
        );
    }

    public function reviewForm(int $id): JsonResponse
    {
        $product = Product::with(['genres.categories'])->findOrFail($id);

        return response()->json([
            'id'          => $product->id,
            'title'       => $product->title,
            'cover_image' => $product->cover_image,
            'type'        => $product->type,
            'slug'        => $product->slug,
            'categories'  => $product->uniqueCategories(),
        ]);
    }

    public function show(Request $request, string $type, string $slug): JsonResponse
    {
        $product = Product::with(['genres', 'gameDetails', 'platforms', 'score'])
            ->where('type', $type)
            ->where('slug', $slug)
            ->firstOrFail();

        $globalScore = $product->score?->global_score;
        $proScore    = $product->score?->pro_score;

        $userReview  = null;
        $trustScore  = null;
        if ($user = $request->user('sanctum')) {
            $review = $product->reviews()
                ->where('user_id', $user->id)
                ->whereNull('banned_at')
                ->first();
            if ($review) {
                $userReview = [
                    'id'             => $review->id,
                    'weighted_score' => $review->weighted_score,
                    'letter_grade'   => $review->letter_grade,
                ];
            }

            $trustScore = $this->scoring->calculateTrustScore($product, $user);
        }

        return response()->json([
            'id'          => $product->id,
            'type'        => $product->type,
            'title'       => $product->title,
            'slug'        => $product->slug,
            'description' => $product->description,
            'cover_image' => $product->cover_image,
            'genres'      => $product->genres->map(fn($g) => [
                'id'   => $g->id,
                'name' => $g->getTranslations('name'),
            ]),
            'game_details' => $product->gameDetails ? [
                'developer' => $product->gameDetails->developer,
                'publisher' => $product->gameDetails->publisher,
                'igdb_id'   => $product->gameDetails->igdb_id,
            ] : null,
            'platforms' => $product->platforms->map(fn($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'type'         => $p->type,
                'release_date' => $p->pivot->release_date,
                'purchase_url' => $p->pivot->purchase_url,
            ]),
            'scores' => [
                'global_score' => $globalScore,
                'global_grade' => $globalScore !== null ? $this->scoring->calculateLetterGrade($globalScore) : null,
                'pro_score'    => $proScore,
                'pro_grade'    => $proScore !== null ? $this->scoring->calculateLetterGrade($proScore) : null,
                'trust_score'  => $trustScore,
                'trust_grade'  => $trustScore !== null ? $this->scoring->calculateLetterGrade($trustScore) : null,
            ],
            'user_review' => $userReview,
        ]);
    }

    private function formatCard(Product $product): array
    {
        $global = $product->score?->global_score ?? 0;
        $pro    = $product->score?->pro_score    ?? 0;
        $best   = max($global, $pro);

        return [
            'id'           => $product->id,
            'type'         => $product->type,
            'slug'         => $product->slug,
            'title'        => $product->title,
            'cover_image'  => $product->cover_image,
            'score'        => $best,
            'letter_grade' => $this->scoring->calculateLetterGrade($best),
            'score_type'   => $global >= $pro ? 'global' : 'pro',
        ];
    }
}
