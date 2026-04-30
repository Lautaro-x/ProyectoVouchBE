<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->role !== UserRole::Admin->value || $user->banned_at !== null) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
