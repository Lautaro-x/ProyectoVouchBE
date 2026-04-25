<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GoogleJwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private GoogleJwtService $googleJwt) {}

    public function googleLogin(Request $request): JsonResponse
    {
        $request->validate(['credential' => 'required|string']);

        $googleUser = $this->googleJwt->verify($request->credential);

        if (!$googleUser) {
            return response()->json(['message' => 'Token inválido'], 401);
        }

        $user = User::where('google_id', $googleUser['sub'])->first();

        if (!$user) {
            $existing = User::where('email', $googleUser['email'])->first();

            if ($existing) {
                if ($existing->google_id !== null) {
                    return response()->json(['message' => 'Email ya asociado a otra cuenta de Google'], 409);
                }
                $existing->update(['google_id' => $googleUser['sub']]);
                $user = $existing;
            } else {
                $user = User::create([
                    'google_id'         => $googleUser['sub'],
                    'name'              => $googleUser['name'],
                    'email'             => $googleUser['email'],
                    'avatar'            => $googleUser['picture'],
                    'email_verified_at' => now(),
                ]);
            }
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }
}
