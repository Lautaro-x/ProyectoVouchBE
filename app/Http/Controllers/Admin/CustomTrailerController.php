<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomTrailerItemRequest;
use App\Http\Requests\UpdateCustomTrailerSectionRequest;
use App\Models\CustomTrailerItem;
use App\Models\CustomTrailerSection;
use Illuminate\Http\JsonResponse;

class CustomTrailerController extends Controller
{
    public function show(): JsonResponse
    {
        $section = CustomTrailerSection::instance();
        $items   = CustomTrailerItem::orderBy('sort_order')->orderBy('id')->get();

        return response()->json([
            'title'     => $section->title,
            'is_active' => $section->is_active,
            'items'     => $items->map(fn($item) => [
                'id'          => $item->id,
                'name'        => $item->name,
                'youtube_url' => $item->youtube_url,
            ]),
        ]);
    }

    public function update(UpdateCustomTrailerSectionRequest $request): JsonResponse
    {
        $section = CustomTrailerSection::instance();
        $section->update($request->validated());

        return response()->json([
            'title'     => $section->title,
            'is_active' => $section->is_active,
        ]);
    }

    public function storeItem(StoreCustomTrailerItemRequest $request): JsonResponse
    {
        $item = CustomTrailerItem::create([
            'name'        => $request->input('name'),
            'youtube_url' => $request->input('youtube_url'),
            'sort_order'  => (CustomTrailerItem::max('sort_order') ?? 0) + 1,
        ]);

        return response()->json([
            'id'          => $item->id,
            'name'        => $item->name,
            'youtube_url' => $item->youtube_url,
        ], 201);
    }

    public function destroyItem(CustomTrailerItem $customTrailerItem): JsonResponse
    {
        $customTrailerItem->delete();
        return response()->json(null, 204);
    }
}
