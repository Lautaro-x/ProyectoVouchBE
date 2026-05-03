<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserFollowerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user         = $request->user();
        $total        = $user->followers()->count();
        $followingIds = $user->following()->pluck('Users.id');

        $query = $user->followers()
            ->select('Users.*')
            ->withCount([
                'reviews as reviews_count'     => fn($q) => $q->whereNull('banned_at'),
                'followers as followers_count',
            ]);

        if ($request->filled('search')) {
            $query->where('Users.name', 'like', '%' . $request->input('search') . '%');
        }

        match ($request->input('sort', 'date_desc')) {
            'date_asc' => $query->orderByPivot('created_at', 'asc'),
            'name_asc' => $query->orderBy('Users.name', 'asc'),
            default    => $query->orderByPivot('created_at', 'desc'),
        };

        $paginator = $query->paginate(24, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        $followers = $paginator->getCollection()->map(fn($u) => [
            'id'              => $u->id,
            'name'            => $u->name,
            'avatar'          => $u->avatar,
            'email'           => $u->show_email ? $u->email : null,
            'badges'          => $u->badges ?? [],
            'social_links'    => collect($u->social_links ?? [])
                ->filter(fn($l) => !empty($l['url']) && ($l['shared'] ?? false))
                ->map(fn($l) => $l['url']),
            'reviews_count'   => $u->reviews_count,
            'followers_count' => $u->followers_count,
            'last_reviews'    => [],
            'card_big_bg'     => $u->card_big_bg,
            'card_mid_bg'     => $u->card_mid_bg,
            'card_mini_bg'    => $u->card_mini_bg,
            'is_following'    => $followingIds->contains($u->id),
        ]);

        return response()->json([
            'total'     => $total,
            'last_page' => $paginator->lastPage(),
            'followers' => $followers,
        ]);
    }
}
