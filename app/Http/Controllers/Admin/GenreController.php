<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GenreController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Genre::with(['categories' => fn($q) => $q->withPivot('weight')])->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100|unique:Genres,name',
            'igdb_genre_id' => 'nullable|integer|unique:Genres,igdb_genre_id',
        ]);

        $data['slug'] = Str::slug($data['name']);

        return response()->json(Genre::create($data), 201);
    }

    public function update(Request $request, Genre $genre): JsonResponse
    {
        $data = $request->validate([
            'name'          => "required|string|max:100|unique:Genres,name,{$genre->id}",
            'igdb_genre_id' => "nullable|integer|unique:Genres,igdb_genre_id,{$genre->id}",
        ]);

        $data['slug'] = Str::slug($data['name']);
        $genre->update($data);

        return response()->json($genre);
    }

    public function destroy(Genre $genre): JsonResponse
    {
        $genre->delete();
        return response()->json(null, 204);
    }

    public function syncCategories(Request $request, Genre $genre): JsonResponse
    {
        $data = $request->validate([
            'categories'             => 'required|array',
            'categories.*.id'        => 'required|exists:Categories,id',
            'categories.*.weight'    => 'required|numeric|min:0.01|max:1',
        ]);

        $sync = collect($data['categories'])->mapWithKeys(
            fn($c) => [$c['id'] => ['weight' => $c['weight']]]
        )->all();

        $genre->categories()->sync($sync);

        return response()->json(
            $genre->load(['categories' => fn($q) => $q->withPivot('weight')])
        );
    }
}
