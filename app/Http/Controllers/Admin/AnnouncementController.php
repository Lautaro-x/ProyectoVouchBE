<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        $announcements = Announcement::orderByDesc('starts_at')
            ->get()
            ->map(fn($a) => [
                'id'       => $a->id,
                'title'    => $a->getTranslations('title'),
                'starts_at' => $a->starts_at->toDateTimeString(),
                'ends_at'   => $a->ends_at->toDateTimeString(),
                'status'   => $this->status($a),
            ]);

        return response()->json($announcements);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'       => 'required|array',
            'title.es'    => 'required|string|max:255',
            'title.en'    => 'required|string|max:255',
            'title.fr'    => 'required|string|max:255',
            'title.pt'    => 'required|string|max:255',
            'title.it'    => 'required|string|max:255',
            'body'        => 'required|array',
            'body.es'     => 'required|string',
            'body.en'     => 'required|string',
            'body.fr'     => 'required|string',
            'body.pt'     => 'required|string',
            'body.it'     => 'required|string',
            'starts_at'   => 'required|date',
            'ends_at'     => 'required|date|after:starts_at',
        ]);

        $announcement = Announcement::create($data);

        return response()->json($this->format($announcement), 201);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        return response()->json($this->format($announcement));
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $data = $request->validate([
            'title'       => 'required|array',
            'title.es'    => 'required|string|max:255',
            'title.en'    => 'required|string|max:255',
            'title.fr'    => 'required|string|max:255',
            'title.pt'    => 'required|string|max:255',
            'title.it'    => 'required|string|max:255',
            'body'        => 'required|array',
            'body.es'     => 'required|string',
            'body.en'     => 'required|string',
            'body.fr'     => 'required|string',
            'body.pt'     => 'required|string',
            'body.it'     => 'required|string',
            'starts_at'   => 'required|date',
            'ends_at'     => 'required|date|after:starts_at',
        ]);

        $announcement->update($data);

        return response()->json($this->format($announcement));
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();
        return response()->json(null, 204);
    }

    private function format(Announcement $a): array
    {
        return [
            'id'        => $a->id,
            'title'     => $a->getTranslations('title'),
            'body'      => $a->getTranslations('body'),
            'starts_at' => $a->starts_at->toDateTimeString(),
            'ends_at'   => $a->ends_at->toDateTimeString(),
        ];
    }

    private function status(Announcement $a): string
    {
        if (!$a->hasAllTranslations()) return 'missing_translations';
        $now = now();
        if ($now->lt($a->starts_at)) return 'upcoming';
        if ($now->gt($a->ends_at))   return 'ended';
        return 'active';
    }
}
