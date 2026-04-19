<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class PublicCardController extends Controller
{
    public function show(User $user): JsonResponse
    {
        $viewer = auth('sanctum')->user();

        return response()->json(array_merge(
            $user->cardData(),
            ['is_following' => $viewer ? $viewer->following()->where('followed_id', $user->id)->exists() : false]
        ));
    }
}
