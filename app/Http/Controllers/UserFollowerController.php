<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserFollowerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $followers = $request->user()
            ->followers()
            ->select('Users.id', 'Users.name', 'Users.badges')
            ->orderByPivot('created_at', 'asc')
            ->limit(100)
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'verified'   => in_array('verificado', $u->badges ?? []),
            ]);

        $total = $request->user()->followers()->count();

        return response()->json([
            'total'     => $total,
            'followers' => $followers,
        ]);
    }
}
