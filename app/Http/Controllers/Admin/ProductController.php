<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ParsesIndexRequest;
use App\Http\Controllers\Controller;
use App\Models\GameDetail;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ParsesIndexRequest;

    public function index(Request $request): JsonResponse
    {
        ['sortBy' => $sortBy, 'sortDir' => $sortDir, 'perPage' => $perPage] =
            $this->paginationParams($request, ['id', 'title', 'type'], 'title');

        $products = Product::with(['genres', 'gameDetails', 'platforms', 'score'])
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->genre_id, fn($q) => $q->whereHas('genres', fn($g) => $g->where('Genres.id', $request->genre_id)))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'         => 'required|in:game,movie,series',
            'genre_ids'    => 'required|array|min:1',
            'genre_ids.*'  => 'exists:Genres,id',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'cover_image'  => 'nullable|url',
            'developer'    => 'nullable|string|max:255',
            'publisher'    => 'nullable|string|max:255',
        ]);

        $data['slug'] = $this->uniqueSlug($data['title']);
        $genreIds     = $data['genre_ids'];

        $product = Product::create(Arr::except($data, ['genre_ids', 'developer', 'publisher']));
        $product->genres()->sync($genreIds);

        if ($data['type'] === 'game') {
            GameDetail::create([
                'product_id' => $product->id,
                'developer'  => $data['developer'] ?? null,
                'publisher'  => $data['publisher'] ?? null,
            ]);
        }

        return response()->json($product->load('gameDetails', 'genres'), 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'genre_ids'    => 'sometimes|array|min:1',
            'genre_ids.*'  => 'exists:Genres,id',
            'title'        => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'cover_image'  => 'nullable|url',
            'developer'    => 'nullable|string|max:255',
            'publisher'    => 'nullable|string|max:255',
        ]);

        if (isset($data['genre_ids'])) {
            $product->genres()->sync($data['genre_ids']);
        }

        if (isset($data['title'])) {
            $data['slug'] = $this->uniqueSlug($data['title'], $product->id);
        }

        $product->update(Arr::except($data, ['genre_ids', 'developer', 'publisher']));

        if ($product->type === 'game') {
            $product->gameDetails()->updateOrCreate(
                ['product_id' => $product->id],
                array_filter([
                    'developer' => $data['developer'] ?? null,
                    'publisher' => $data['publisher'] ?? null,
                ], fn($v) => $v !== null)
            );
        }

        return response()->json($product->load('gameDetails', 'genres', 'platforms'));
    }

    public function purchaseLinks(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'platforms'                => 'required|array',
            'platforms.*.platform_id'  => 'required|exists:Platforms,id',
            'platforms.*.purchase_url' => 'nullable|url|max:500',
        ]);

        foreach ($data['platforms'] as $item) {
            $product->platforms()->updateExistingPivot($item['platform_id'], [
                'purchase_url' => $item['purchase_url'] ?? null,
            ]);
        }

        return response()->json($product->load('platforms'));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();
        return response()->json(null, 204);
    }

    private function uniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug  = Str::slug($title);
        $count = Product::where('slug', 'like', "{$slug}%")
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->count();

        return $count > 0 ? "{$slug}-{$count}" : $slug;
    }
}
