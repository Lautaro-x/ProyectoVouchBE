<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function googleLogin(Request $request): JsonResponse
    {
        $request->validate(['credential' => 'required|string']);

        $googleUser = $this->verifyGoogleToken($request->credential);

        if (!$googleUser) {
            return response()->json(['message' => 'Token inválido'], 401);
        }

        $user = User::where('google_id', $googleUser['sub'])
            ->orWhere('email', $googleUser['email'])
            ->first();

        if ($user) {
            $user->update([
                'google_id' => $googleUser['sub'],
                'avatar'    => $googleUser['picture'],
            ]);
        } else {
            $user = User::create([
                'google_id'         => $googleUser['sub'],
                'name'              => $googleUser['name'],
                'email'             => $googleUser['email'],
                'avatar'            => $googleUser['picture'],
                'email_verified_at' => now(),
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    private function verifyGoogleToken(string $credential): ?array
    {
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $credential,
        ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        if ($data['aud'] !== config('services.google.client_id')) {
            return null;
        }

        return $data;
    }
}
