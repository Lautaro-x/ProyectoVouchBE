<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function follow(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json(['error' => 'Cannot follow yourself'], 422);
        }

        $request->user()->following()->syncWithoutDetaching([$user->id]);

        return response()->json(['message' => 'ok']);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $request->user()->following()->detach($user->id);

        return response()->json(['message' => 'ok']);
    }
}
