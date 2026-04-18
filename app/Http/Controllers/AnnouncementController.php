<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    public function active(): JsonResponse
    {
        $now = now();

        $announcements = Announcement::where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->get()
            ->filter(fn($a) => $a->hasAllTranslations())
            ->values()
            ->map(fn($a) => [
                'id'    => $a->id,
                'title' => $a->getTranslations('title'),
                'body'  => $a->getTranslations('body'),
            ]);

        return response()->json($announcements);
    }
}
