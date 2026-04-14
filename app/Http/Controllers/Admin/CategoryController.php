<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $allowed = ['id', 'name', 'slug'];
        $sortBy  = in_array($request->sort_by, $allowed) ? $request->sort_by : 'id';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';
        $perPage = min((int) $request->get('per_page', 25), 100);

        $query = Category::query();

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
            Category::where('slug', $slug)->exists(),
            422,
            'A category with this English name already exists.'
        );

        $category = Category::create(['name' => $data['name'], 'slug' => $slug]);

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category): JsonResponse
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
            $category->slug !== $slug && Category::where('slug', $slug)->exists(),
            422,
            'A category with this English name already exists.'
        );

        $category->setTranslations('name', $data['name']);
        $category->slug = $slug;
        $category->save();

        return response()->json($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();
        return response()->json(null, 204);
    }
}
