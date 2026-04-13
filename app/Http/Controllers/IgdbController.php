<?php

namespace App\Http\Controllers;

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

        return response()->json($this->igdb->search($request->q));
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'igdb_id'  => 'required|integer',
            'genre_id' => 'required|exists:Genres,id',
        ]);

        $game = $this->igdb->find($request->igdb_id);

        if (!$game) {
            return response()->json(['message' => 'Juego no encontrado en IGDB'], 404);
        }

        $product = $this->importer->importGame($game, $request->genre_id);

        if (!$product) {
            return response()->json(['message' => 'Este juego ya fue importado'], 409);
        }

        return response()->json(
            $product->load('gameDetails', 'platforms', 'genre'),
            201
        );
    }
}
