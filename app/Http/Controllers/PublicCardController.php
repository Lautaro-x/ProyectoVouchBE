<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class PublicCardController extends Controller
{
    public function show(User $user): JsonResponse
    {
        $lastReviews = $user->reviews()
            ->whereHas('product', fn($q) => $q->where('type', 'game'))
            ->with(['product:id,type,title,slug,cover_image'])
            ->whereNull('banned_at')
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn($r) => [
                'weighted_score' => $r->weighted_score,
                'letter_grade'   => $r->letter_grade,
                'product'        => [
                    'title'       => $r->product->title,
                    'slug'        => $r->product->slug,
                    'type'        => $r->product->type,
                    'cover_image' => $r->product->cover_image,
                ],
            ]);

        $user->loadCount([
            'reviews as reviews_count' => fn($q) => $q->whereNull('banned_at'),
            'followers as followers_count',
        ]);

        $sharedSocials = collect($user->social_links ?? [])
            ->filter(fn($link) => !empty($link['url']) && ($link['shared'] ?? false))
            ->map(fn($link) => $link['url']);

        $viewer = auth('sanctum')->user();

        return response()->json([
            'id'              => $user->id,
            'name'            => $user->name,
            'avatar'          => $user->avatar,
            'email'           => $user->show_email ? $user->email : null,
            'badges'          => $user->badges ?? [],
            'social_links'    => $sharedSocials,
            'reviews_count'   => $user->reviews_count,
            'followers_count' => $user->followers_count,
            'last_reviews'    => $lastReviews,
            'card_big_bg'     => $user->card_big_bg,
            'card_mid_bg'     => $user->card_mid_bg,
            'card_mini_bg'    => $user->card_mini_bg,
            'is_following'    => $viewer ? $viewer->following()->where('followed_id', $user->id)->exists() : false,
        ]);
    }
}
