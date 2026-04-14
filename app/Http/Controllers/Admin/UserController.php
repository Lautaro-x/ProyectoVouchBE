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
        $allowed = ['id', 'name', 'email', 'created_at'];
        $sortBy  = in_array($request->sort_by, $allowed) ? $request->sort_by : 'id';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';
        $perPage = min((int) $request->get('per_page', 25), 100);

        $users = User::select(['id', 'name', 'email', 'role', 'avatar', 'badges', 'banned_at', 'ban_reason', 'created_at'])
            ->when($request->banned, fn($q) => $q->whereNotNull('banned_at'))
            ->when($request->role, fn($q) => $q->where('role', $request->role))
            ->when($request->search, fn($q) => $q->where(function ($inner) use ($request) {
                $inner->where('name', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);

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
