<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ParsesIndexRequest;
use App\Http\Controllers\Controller;
use App\Models\Platform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlatformController extends Controller
{
    use ParsesIndexRequest;

    public function index(Request $request): JsonResponse
    {
        ['sortBy' => $sortBy, 'sortDir' => $sortDir, 'perPage' => $perPage] =
            $this->paginationParams($request, ['id', 'name', 'type']);

        $query = Platform::query();

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->all === '1') {
            return response()->json($query->orderBy($sortBy, $sortDir)->get());
        }

        return response()->json($query->orderBy($sortBy, $sortDir)->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:Platforms,name',
            'type' => 'required|in:console,pc,streaming',
        ]);

        $data['slug'] = Str::slug($data['name']);

        return response()->json(Platform::create($data), 201);
    }

    public function update(Request $request, Platform $platform): JsonResponse
    {
        $data = $request->validate([
            'name' => "required|string|max:100|unique:Platforms,name,{$platform->id}",
            'type' => 'required|in:console,pc,streaming',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $platform->update($data);

        return response()->json($platform);
    }

    public function destroy(Platform $platform): JsonResponse
    {
        $platform->delete();
        return response()->json(null, 204);
    }
}
