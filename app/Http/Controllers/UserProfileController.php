<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'avatar'         => $user->avatar,
            'role'           => $user->role,
            'badges'         => $user->badges ?? [],
            'show_email'     => (bool) $user->show_email,
            'reviews_public' => (bool) $user->reviews_public,
            'social_links'   => $user->social_links ?? [],
        ]);
    }

    public function cardData(Request $request): JsonResponse
    {
        $user = $request->user();

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
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name'                   => 'sometimes|string|max:25',
            'avatar'                 => 'sometimes|nullable|url|max:500',
            'show_email'             => 'sometimes|boolean',
            'reviews_public'         => 'sometimes|boolean',
            'social_links'           => 'sometimes|nullable|array',
            'social_links.*.url'     => 'nullable|url|max:500',
            'social_links.*.shared'  => 'boolean',
            'card_big_bg'            => 'sometimes|nullable|url|max:500',
            'card_mid_bg'            => 'sometimes|nullable|url|max:500',
            'card_mini_bg'           => 'sometimes|nullable|url|max:500',
        ]);

        $request->user()->update($request->only([
            'name', 'avatar', 'show_email', 'reviews_public', 'social_links',
            'card_big_bg', 'card_mid_bg', 'card_mini_bg',
        ]));

        return response()->json(['message' => 'ok']);
    }
}
