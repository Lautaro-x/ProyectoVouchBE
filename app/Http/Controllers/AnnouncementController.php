<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersAudience;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    use FiltersAudience;

    public function active(Request $request): JsonResponse
    {
        $user          = $request->user();
        $now           = now();
        $userAudiences = $this->resolveAudiences($user);

        $announcements = Announcement::where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->whereIn('audience', $userAudiences)
            ->get()
            ->filter(fn($a) => $a->hasAllTranslations())
            ->values()
            ->map(fn($a) => [
                'id'       => $a->id,
                'title'    => $a->getTranslations('title'),
                'body'     => $a->getTranslations('body'),
                'audience' => $a->audience,
            ]);

        return response()->json($announcements);
    }
}
