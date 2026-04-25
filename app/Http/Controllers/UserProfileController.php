<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'avatar'       => $user->avatar,
            'role'         => $user->role,
            'badges'       => $user->badges ?? [],
            'social_links' => $user->social_links ?? [],
        ]);
    }

    public function cardData(Request $request): JsonResponse
    {
        return response()->json($request->user()->cardData());
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $request->user()->update($request->only([
            'name', 'avatar', 'social_links',
            'card_big_bg', 'card_mid_bg', 'card_mini_bg',
        ]));

        return response()->json(['message' => 'ok']);
    }
}
