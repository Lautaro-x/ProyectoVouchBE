<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PublicCardController extends Controller
{
    public function show(User $user): JsonResponse
    {
        $viewer = auth('sanctum')->user();

        $isFollowing = $viewer
            ? DB::table('Follows')
                ->where('follower_id', $viewer->id)
                ->where('followed_id', $user->id)
                ->exists()
            : false;

        return response()->json(array_merge(
            $user->cardData(),
            ['is_following' => $isFollowing]
        ));
    }
}
