<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameDetail;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::with(['genre', 'gameDetails', 'platforms', 'score'])
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->genre_id, fn($q) => $q->where('genre_id', $request->genre_id))
            ->orderBy('title')
            ->paginate(20);

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'        => 'required|in:game,movie,series',
            'genre_id'    => 'required|exists:Genres,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|url',
            'developer'   => 'nullable|string|max:255',
            'publisher'   => 'nullable|string|max:255',
        ]);

        $data['slug'] = $this->uniqueSlug($data['title']);

        $product = Product::create($data);

        if ($data['type'] === 'game') {
            GameDetail::create([
                'product_id' => $product->id,
                'developer'  => $data['developer'] ?? null,
                'publisher'  => $data['publisher'] ?? null,
            ]);
        }

        return response()->json($product->load('gameDetails', 'genre'), 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'genre_id'    => 'sometimes|exists:Genres,id',
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|url',
            'developer'   => 'nullable|string|max:255',
            'publisher'   => 'nullable|string|max:255',
        ]);

        if (isset($data['title'])) {
            $data['slug'] = $this->uniqueSlug($data['title'], $product->id);
        }

        $product->update($data);

        if ($product->type === 'game') {
            $product->gameDetails()->updateOrCreate(
                ['product_id' => $product->id],
                array_filter([
                    'developer' => $data['developer'] ?? null,
                    'publisher' => $data['publisher'] ?? null,
                ], fn($v) => $v !== null)
            );
        }

        return response()->json($product->load('gameDetails', 'genre', 'platforms'));
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
