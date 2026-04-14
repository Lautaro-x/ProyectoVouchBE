<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GenreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $allowed = ['id', 'name', 'igdb_genre_id'];
        $sortBy  = in_array($request->sort_by, $allowed) ? $request->sort_by : 'id';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';
        $perPage = min((int) $request->get('per_page', 25), 100);

        $query = Genre::with(['categories' => fn($q) => $q->withPivot('weight')]);

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                foreach (['en', 'es', 'fr', 'pt', 'it'] as $locale) {
                    $q->orWhereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(name, '$.$locale')) LIKE ?",
                        ["%{$search}%"]
                    );
                }
            });
        }

        if ($sortBy === 'name') {
            $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) {$sortDir}");
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        if ($request->all === '1') {
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
            'categories'          => 'required|array',
            'categories.*.id'     => 'required|exists:Categories,id',
            'categories.*.weight' => 'required|numeric|min:0.01|max:1',
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
