<?php

namespace App\Http\Controllers;

use App\Models\UpcomingGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UpcomingGameController extends Controller
{
    public function index(): JsonResponse
    {
        $games = Cache::remember('upcoming_games_public', 300, fn() =>
            UpcomingGame::where('is_visible', true)
                ->orderByRaw('release_date IS NULL, release_date ASC')
                ->orderBy('hypes', 'desc')
                ->get()
        );

        return response()->json($games);
    }
}
