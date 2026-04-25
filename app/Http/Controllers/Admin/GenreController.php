<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ParsesIndexRequest;
use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GenreController extends Controller
{
    use ParsesIndexRequest;

    public function index(Request $request): JsonResponse
    {
        ['sortBy' => $sortBy, 'sortDir' => $sortDir, 'perPage' => $perPage] =
            $this->paginationParams($request, ['id', 'name', 'igdb_genre_id']);

        if ($request->input('all') === '1' && !$request->filled('search')) {
            return response()->json(
                Cache::remember('admin_genres_all', 3600, fn() =>
                    Genre::with(['categories' => fn($q) => $q->withPivot('weight')])
                        ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) asc")
                        ->get()
                )
            );
        }

        $query = Genre::with(['categories' => fn($q) => $q->withPivot('weight')]);

        if ($request->filled('search')) {
            $query->searchTranslatable($request->input('search'));
        }

        if ($sortBy === 'name') {
            $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) {$sortDir}");
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        if ($request->input('all') === '1') {
            return response()->json($query->get());
        }

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => 'required|array',
            'name.en' => 'required|string|max:100',
            'name.es' => 'nullable|string|max:100',
            'name.fr' => 'nullable|string|max:100',
            'name.pt' => 'nullable|string|max:100',
            'name.it' => 'nullable|string|max:100',
        ]);

        $slug = Str::slug($data['name']['en']);

        abort_if(
            Genre::where('slug', $slug)->exists(),
            422,
            'A genre with this English name already exists.'
        );

        $genre = Genre::create(['name' => $data['name'], 'slug' => $slug]);
        Cache::forget('admin_genres_all');

        return response()->json($genre, 201);
    }

    public function update(Request $request, Genre $genre): JsonResponse
    {
        $data = $request->validate([
            'name'    => 'required|array',
            'name.en' => 'required|string|max:100',
            'name.es' => 'nullable|string|max:100',
            'name.fr' => 'nullable|string|max:100',
            'name.pt' => 'nullable|string|max:100',
            'name.it' => 'nullable|string|max:100',
        ]);

        $slug = Str::slug($data['name']['en']);

        abort_if(
            $genre->slug !== $slug && Genre::where('slug', $slug)->exists(),
            422,
            'A genre with this English name already exists.'
        );

        $genre->setTranslations('name', $data['name']);
        $genre->slug = $slug;
        $genre->save();
        Cache::forget('admin_genres_all');

        return response()->json($genre);
    }

    public function destroy(Genre $genre): JsonResponse
    {
        $genre->delete();
        Cache::forget('admin_genres_all');
        return response()->json(null, 204);
    }

    public function syncCategories(Request $request, Genre $genre): JsonResponse
    {
        $data = $request->validate([
            'categories'          => 'required|array',
            'categories.*.id'     => 'required|exists:Categories,id',
            'categories.*.weight' => 'required|numeric|min:0.01|max:1',
        ]);

        $sync = collect($data['categories'])->mapWithKeys(
            fn($c) => [$c['id'] => ['weight' => $c['weight']]]
        )->all();

        $genre->categories()->sync($sync);
        Cache::forget('admin_genres_all');

        return response()->json(
            $genre->load(['categories' => fn($q) => $q->withPivot('weight')])
        );
    }
}
