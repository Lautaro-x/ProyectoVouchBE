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
            'badges'       => $user->badges ?? [],
            'social_links' => $user->social_links ?? [],
        ]);
    }

    public function cardData(Request $request): JsonResponse
    {
        return response()->json($request->user()->cardData());
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name'                   => 'sometimes|string|max:25',
            'avatar'                 => 'sometimes|nullable|url|max:500',
            'social_links'           => 'sometimes|nullable|array',
            'social_links.*.url'     => 'nullable|url|max:500',
            'social_links.*.shared'  => 'boolean',
            'card_big_bg'            => 'sometimes|nullable|url|max:500',
            'card_mid_bg'            => 'sometimes|nullable|url|max:500',
            'card_mini_bg'           => 'sometimes|nullable|url|max:500',
        ]);

        $request->user()->update($request->only([
            'name', 'avatar', 'social_links',
            'card_big_bg', 'card_mid_bg', 'card_mini_bg',
        ]));

        return response()->json(['message' => 'ok']);
    }
}
