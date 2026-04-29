<?php

namespace App\Http\Controllers;

use App\Models\GameDetail;
use App\Models\Product;
use App\Models\UpcomingGame;
use Illuminate\Support\Str;
use App\Services\IgdbService;
use App\Services\ProductImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IgdbController extends Controller
{
    public function __construct(
        private IgdbService $igdb,
        private ProductImportService $importer
    ) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2']);

        $games     = $this->igdb->search($request->input('q'));
        $importedIds = GameDetail::whereIn('igdb_id', array_column($games, 'id'))
            ->pluck('igdb_id')
            ->flip()
            ->all();

        $result = array_map(
            fn($g) => array_merge($g, ['already_imported' => isset($importedIds[$g['id']])]),
            $games
        );

        return response()->json($result);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['igdb_id' => 'required|integer']);

        $game = $this->igdb->find($request->input('igdb_id'));

        if (!$game) {
            return response()->json(['message' => 'Juego no encontrado en IGDB'], 404);
        }

        $product = $this->importer->importGame($game);
        $status  = $product->wasRecentlyCreated ? 201 : 200;

        return response()->json(
            $product->load('gameDetails', 'platforms', 'genres'),
            $status
        );
    }

    public function importRecent(): JsonResponse
    {
        $games   = $this->igdb->recentGames(48);
        $results = ['imported' => [], 'skipped' => [], 'errors' => []];

        foreach ($games as $game) {
            if (GameDetail::where('igdb_id', $game['id'])->exists()) {
                $results['skipped'][] = $game['name'];
                continue;
            }

            try {
                $product             = $this->importer->importGame($game);
                $results['imported'][] = $product->title;
            } catch (\Throwable) {
                $results['errors'][] = $game['name'];
            }
        }

        return response()->json($results);
    }

    public function syncEarlyAccess(): JsonResponse
    {
        $details = GameDetail::with('product')
            ->where('status', 4)
            ->where(fn($q) => $q
                ->whereNull('igdb_synced_at')
                ->orWhere('igdb_synced_at', '<', now()->subWeek())
            )
            ->limit(1000)
            ->get();

        $results = ['imported' => [], 'skipped' => [], 'errors' => []];

        foreach ($details as $detail) {
            try {
                $game = $this->igdb->find($detail->igdb_id);

                if (!$game) {
                    $results['skipped'][] = $detail->product->title;
                    continue;
                }

                $this->importer->importGame($game);
                $results['imported'][] = $detail->product->title;
            } catch (\Throwable) {
                $results['errors'][] = $detail->product->title;
            }
        }

        return response()->json($results);
    }

    public function syncUpcoming(): JsonResponse
    {
        $games = $this->igdb->upcomingGames();

        UpcomingGame::truncate();

        $usedSlugs = [];
        $imported  = 0;
        $errors    = [];

        foreach ($games as $game) {
            try {
                $developer = null;
                foreach ($game['involved_companies'] ?? [] as $entry) {
                    if (!empty($entry['developer'])) {
                        $developer = $entry['company']['name'];
                        break;
                    }
                }

                $officialUrl = null;
                foreach ($game['websites'] ?? [] as $site) {
                    if (($site['category'] ?? null) === 1 && !empty($site['url'])) {
                        $officialUrl = $site['url'];
                        break;
                    }
                }

                $trailerId = null;
                foreach ($game['videos'] ?? [] as $video) {
                    if (!empty($video['video_id'])) {
                        $trailerId = $video['video_id'];
                        break;
                    }
                }

                $baseSlug = Str::slug($game['name']) ?: 'game-' . $game['id'];
                $slug     = isset($usedSlugs[$baseSlug]) ? $baseSlug . '-' . $game['id'] : $baseSlug;
                $usedSlugs[$slug] = true;

                UpcomingGame::create([
                    'igdb_id'            => $game['id'],
                    'title'              => $game['name'],
                    'slug'               => $slug,
                    'release_date'       => isset($game['first_release_date']) ? date('Y-m-d', $game['first_release_date']) : null,
                    'cover_image'        => isset($game['cover']['url']) ? 'https:' . str_replace('t_thumb', 't_cover_big', $game['cover']['url']) : null,
                    'trailer_youtube_id' => $trailerId,
                    'developer'          => $developer,
                    'official_url'       => $officialUrl,
                    'hypes'              => $game['hypes'] ?? null,
                    'is_visible'         => true,
                    'igdb_synced_at'     => now(),
                ]);

                $imported++;
            } catch (\Throwable) {
                $errors[] = $game['name'] ?? 'unknown';
            }
        }

        Cache::forget('upcoming_games_public');

        return response()->json([
            'imported' => ["Se importaron {$imported} juegos upcoming"],
            'skipped'  => [],
            'errors'   => $errors,
        ]);
    }

    public function discover(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:3|max:100']);

        $q        = trim($request->input('q'));
        $cacheKey = 'igdb_discover_' . md5(strtolower($q));

        $results = Cache::remember($cacheKey, 600, function () use ($q) {
            $games = $this->igdb->search($q);

            return array_map(fn($g) => [
                'igdb_id' => $g['id'],
                'name'    => $g['name'],
                'cover'   => isset($g['cover']['url'])
                    ? 'https:' . str_replace('t_thumb', 't_cover_small', $g['cover']['url'])
                    : null,
                'year'    => isset($g['first_release_date'])
                    ? date('Y', $g['first_release_date'])
                    : null,
            ], $games);
        });

        return response()->json($results);
    }

    public function discoverImport(Request $request): JsonResponse
    {
        $request->validate(['igdb_id' => 'required|integer']);

        $igdbId   = (int) $request->input('igdb_id');
        $existing = GameDetail::with('product')->where('igdb_id', $igdbId)->first();

        if ($existing) {
            return response()->json([
                'type' => $existing->product->type,
                'slug' => $existing->product->slug,
            ]);
        }

        $game = $this->igdb->find($igdbId);

        if (!$game) {
            return response()->json(['message' => 'Juego no encontrado en IGDB'], 404);
        }

        $product = $this->importer->importGame($game);

        return response()->json([
            'type' => $product->type,
            'slug' => $product->slug,
        ], 201);
    }

    public function syncProduct(Product $product): JsonResponse
    {
        $igdbId = $product->gameDetails?->igdb_id;

        if (!$igdbId) {
            return response()->json(['message' => 'El producto no tiene igdb_id'], 422);
        }

        $game = $this->igdb->find($igdbId);

        if (!$game) {
            return response()->json(['message' => 'Juego no encontrado en IGDB'], 404);
        }

        $updated = $this->importer->importGame($game);

        return response()->json([
            'imported' => [$updated->title],
            'skipped'  => [],
            'errors'   => [],
        ]);
    }
}
