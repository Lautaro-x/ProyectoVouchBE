<?php

namespace App\Http\Controllers;

use App\Services\BadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    public function __construct(private BadgeService $badges) {}

    public function progress(Request $request): JsonResponse
    {
        return response()->json($this->badges->getProgress($request->user()));
    }

    public function claim(Request $request, string $badge): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            $this->badges->award($user, $badge);
            return response()->json(['message' => 'ok']);
        }

        if (!$this->badges->claim($user, $badge)) {
            return response()->json(['error' => 'Badge not claimable'], 422);
        }

        return response()->json(['message' => 'ok']);
    }

    public function remove(Request $request, string $badge): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            abort(403);
        }

        $this->badges->revoke($user, $badge);

        return response()->json(['message' => 'ok']);
    }
}
