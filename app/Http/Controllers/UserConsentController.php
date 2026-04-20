<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserConsentController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'show_email'             => (bool) $user->show_email,
            'consent_follower_score' => (bool) $user->consent_follower_score,
            'is_verified'            => in_array('verificado', $user->badges ?? []),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'show_email'             => 'sometimes|boolean',
            'consent_follower_score' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $data = $request->only(['show_email', 'consent_follower_score']);

        $isVerified = in_array('verificado', $user->badges ?? []);
        if (!$isVerified) {
            $data['consent_follower_score'] = false;
        }

        $user->update($data);

        return response()->json(['message' => 'ok']);
    }
}
