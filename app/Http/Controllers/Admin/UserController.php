<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::select(['id', 'name', 'email', 'role', 'avatar', 'badges', 'banned_at', 'ban_reason', 'created_at'])
            ->when($request->banned, fn($q) => $q->whereNotNull('banned_at'))
            ->when($request->role, fn($q) => $q->where('role', $request->role))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(
            $user->load(['reviews.product', 'following', 'followers'])
        );
    }

    public function ban(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'ban_reason' => 'nullable|string|max:255',
        ]);

        $user->update([
            'banned_at'  => now(),
            'ban_reason' => $data['ban_reason'] ?? null,
        ]);

        $user->tokens()->delete();

        return response()->json(['message' => 'Usuario suspendido.']);
    }

    public function unban(User $user): JsonResponse
    {
        $user->update(['banned_at' => null, 'ban_reason' => null]);

        return response()->json(['message' => 'Usuario reactivado.']);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => 'required|in:user,critic,admin',
        ]);

        $user->update($data);

        return response()->json($user);
    }
}
