<?php

namespace App\Http\Controllers;

use App\Services\ScoringService;
use App\Models\CustomTrailerItem;
use App\Models\CustomTrailerSection;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(private ScoringService $scoring) {}

    public function games(Request $request): JsonResponse
    {
        $paginator = Product::with(['score', 'gameDetails'])
            ->addSelect([
                'Products.*',
                'latest_release' => DB::table('Product_x_Platform')
                    ->selectRaw('MAX(release_date)')
                    ->whereColumn('product_id', 'Products.id'),
            ])
            ->where('type', 'game')
            ->when(
                $request->filled('search'),
                fn($q) => $q->where('Products.title', 'like', '%' . $request->input('search') . '%')
            )
            ->when(
                $request->filled('filter_type') && $request->filled('filter_value'),
                function ($q) use ($request) {
                    $type  = $request->input('filter_type');
                    $value = $request->input('filter_value');
                    return match ($type) {
                        'genre'              => $q->whereHas('genres', fn ($g) => $g->where('id', $value)),
                        'developer'          => $q->whereHas('gameDetails', fn ($g) => $g->where('developer', $value)),
                        'publisher'          => $q->whereHas('gameDetails', fn ($g) => $g->where('publisher', $value)),
                        'franchise'          => $q->whereHas('gameDetails', fn ($g) => $g->where('franchise', $value)),
                        'theme'              => $q->whereHas('gameDetails', fn ($g) => $g->whereJsonContains('themes', $value)),
                        'game_mode'          => $q->whereHas('gameDetails', fn ($g) => $g->whereJsonContains('game_modes', $value)),
                        'player_perspective' => $q->whereHas('gameDetails', fn ($g) => $g->whereJsonContains('player_perspectives', $value)),
                        default              => $q,
                    };
                }
            )
            ->orderByDesc('latest_release')
            ->paginate(12);

        $followedIds = [];
        if ($user = $request->user('sanctum')) {
            $followedIds = $user->following()->pluck('followed_id')->toArray();
        }

        $items     = collect($paginator->items());
        $reviewMap = $this->followerReviewMap($followedIds, $items->pluck('id')->all());

        return response()->json([
            'data'         => $items->map(fn(Product $p) => array_merge(
                $this->formatCard($p),
                [
                    'trust_grade' => !empty($followedIds)
                        ? (($ts = $this->scoring->trustScoreFromIds($p, $followedIds)) !== null
                            ? $this->scoring->calculateLetterGrade($ts)
                            : null)
                        : null,
                    'follower_review' => ($fr = $reviewMap->get($p->id)) ? [
                        'user_name'    => $fr->user_name,
                        'letter_grade' => $fr->letter_grade,
                    ] : null,
                ]
            )),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'total'        => $paginator->total(),
        ]);
    }

    public function relevant(Request $request): JsonResponse
    {
        $products = Cache::remember('relevant_products', 600, fn () =>
            Product::with(['score', 'gameDetails', 'platforms'])
                ->addSelect([
                    'Products.*',
                    'latest_release' => DB::table('Product_x_Platform')
                        ->selectRaw('MAX(release_date)')
                        ->whereColumn('product_id', 'Products.id'),
                ])
                ->whereHas('score', fn($q) =>
                    $q->whereRaw('GREATEST(COALESCE(global_score, 0), COALESCE(pro_score, 0)) >= 8.0')
                )
                ->orderByDesc('latest_release')
                ->limit(10)
                ->get()
        );

        $followedIds = [];
        if ($user = $request->user('sanctum')) {
            $followedIds = $user->following()->pluck('followed_id')->toArray();
        }

        $reviewMap = $this->followerReviewMap($followedIds, $products->pluck('id')->all());

        return response()->json(
            $products->map(fn(Product $p) => array_merge(
                $this->formatCard($p),
                [
                    'trust_grade' => !empty($followedIds)
                        ? (($ts = $this->scoring->trustScoreFromIds($p, $followedIds)) !== null
                            ? $this->scoring->calculateLetterGrade($ts)
                            : null)
                        : null,
                    'follower_review' => ($fr = $reviewMap->get($p->id)) ? [
                        'user_name'    => $fr->user_name,
                        'letter_grade' => $fr->letter_grade,
                    ] : null,
                ]
            ))->values()
        );
    }

    private function followerReviewMap(array $followedIds, array $productIds): \Illuminate\Support\Collection
    {
        if (empty($followedIds) || empty($productIds)) {
            return collect();
        }

        return DB::table('reviews')
            ->join('users', 'users.id', '=', 'reviews.user_id')
            ->whereIn('reviews.product_id', $productIds)
            ->whereIn('reviews.user_id', $followedIds)
            ->whereNull('reviews.banned_at')
            ->where('reviews.created_at', '>=', now()->subMonth())
            ->orderByDesc('reviews.created_at')
            ->select('reviews.product_id', 'reviews.letter_grade', 'users.name as user_name')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');
    }

    public function trailers(): JsonResponse
    {
        $section = CustomTrailerSection::instance();

        if ($section->is_active) {
            $items = CustomTrailerItem::orderBy('sort_order')->orderBy('id')->get();
            return response()->json([
                'section_title' => $section->title,
                'items'         => $items->map(fn($item) => [
                    'id'                 => $item->id,
                    'title'              => $item->name,
                    'slug'               => '',
                    'type'               => '',
                    'trailer_youtube_id' => $this->extractYoutubeId($item->youtube_url),
                ]),
            ]);
        }

        $items = Product::with('gameDetails')
            ->whereHas('gameDetails', fn($q) => $q->whereNotNull('trailer_youtube_id'))
            ->orderByRaw('COALESCE((SELECT MAX(release_date) FROM `Product_x_Platform` WHERE product_id = Products.id), Products.created_at) DESC')
            ->limit(20)
            ->get()
            ->map(fn(Product $p) => [
                'id'                 => $p->id,
                'title'              => $p->title,
                'slug'               => $p->slug,
                'type'               => $p->type,
                'trailer_youtube_id' => $p->gameDetails->trailer_youtube_id,
            ]);

        return response()->json([
            'section_title' => null,
            'items'         => $items,
        ]);
    }

    private function extractYoutubeId(string $url): string
    {
        preg_match(
            '/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
            $url,
            $matches
        );
        return $matches[1] ?? $url;
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

        $userReview    = null;
        $trustScore    = null;
        $followerScore = null;
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

            $followingIds  = $user->following()->pluck('followed_id')->toArray();
            $followerIds   = $user->followers()->pluck('follower_id')->toArray();
            $trustScore    = $this->scoring->trustScoreFromIds($product, $followingIds);
            $followerScore = $this->scoring->followerScoreFromIds($product, $user, $followerIds);
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
                'igdb_id'                 => $product->gameDetails->igdb_id,
                'developer'               => $product->gameDetails->developer,
                'publisher'               => $product->gameDetails->publisher,
                'storyline'               => $product->gameDetails->storyline,
                'igdb_rating'             => $product->gameDetails->igdb_rating,
                'igdb_grade'              => $product->gameDetails->igdb_rating !== null
                    ? $this->scoring->calculateLetterGrade($product->gameDetails->igdb_rating)
                    : null,
                'igdb_rating_count'       => $product->gameDetails->igdb_rating_count,
                'aggregated_rating'       => $product->gameDetails->aggregated_rating,
                'aggregated_rating_count' => $product->gameDetails->aggregated_rating_count,
                'hypes'                   => $product->gameDetails->hypes,
                'follows'                 => $product->gameDetails->follows,
                'status'                  => $product->gameDetails->status,
                'category'                => $product->gameDetails->category,
                'franchise'               => $product->gameDetails->franchise,
                'trailer_youtube_id'      => $product->gameDetails->trailer_youtube_id,
                'pegi_rating'             => $product->gameDetails->pegi_rating,
                'esrb_rating'             => $product->gameDetails->esrb_rating,
                'gog_url'                 => $product->gameDetails->gog_url,
                'epic_url'                => $product->gameDetails->epic_url,
                'official_url'            => $product->gameDetails->official_url,
                'game_modes'              => $product->gameDetails->game_modes,
                'themes'                  => $product->gameDetails->themes,
                'player_perspectives'     => $product->gameDetails->player_perspectives,
                'screenshots'             => $product->gameDetails->screenshots,
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
                'trust_score'     => $trustScore,
                'trust_grade'     => $trustScore !== null ? $this->scoring->calculateLetterGrade($trustScore) : null,
                'follower_score'  => $followerScore,
                'follower_grade'  => $followerScore !== null ? $this->scoring->calculateLetterGrade($followerScore) : null,
            ],
            'user_review' => $userReview,
        ]);
    }

    private function formatCard(Product $product): array
    {
        $global = $product->score?->global_score;
        $pro    = $product->score?->pro_score;

        $base = [
            'id'          => $product->id,
            'type'        => $product->type,
            'slug'        => $product->slug,
            'title'       => $product->title,
            'cover_image' => $product->cover_image,
        ];

        if ($global !== null || $pro !== null) {
            $best = max($global ?? 0, $pro ?? 0);
            return array_merge($base, [
                'letter_grade' => $this->scoring->calculateLetterGrade($best),
                'score_type'   => ($global ?? 0) >= ($pro ?? 0) ? 'global' : 'pro',
            ]);
        }

        $igdbRating = $product->gameDetails?->igdb_rating;

        if ($igdbRating !== null) {
            return array_merge($base, [
                'letter_grade' => $this->scoring->calculateLetterGrade($igdbRating),
                'score_type'   => 'igdb',
            ]);
        }

        return array_merge($base, [
            'letter_grade' => null,
            'score_type'   => 'none',
        ]);
    }
}
