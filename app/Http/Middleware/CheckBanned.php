<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isBanned()) {
            return response()->json(['message' => 'Tu cuenta ha sido suspendida.'], 403);
        }

        return $next($request);
    }
}
