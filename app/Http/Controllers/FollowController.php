<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    use ApiResponse;

    public function follow(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return $this->error('Cannot follow yourself');
        }

        $request->user()->following()->syncWithoutDetaching([$user->id]);

        return $this->ok();
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $request->user()->following()->detach($user->id);

        return $this->ok();
    }
}
