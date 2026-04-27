<?php

namespace App\Http\Controllers;

use App\Models\GameDetail;
use App\Models\Product;
use App\Services\IgdbService;
use App\Services\ProductImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
