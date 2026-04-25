<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Services\BadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    use ApiResponse;

    public function __construct(private BadgeService $badges) {}

    public function progress(Request $request): JsonResponse
    {
        return $this->ok($this->badges->getProgress($request->user()));
    }

    public function claim(Request $request, string $badge): JsonResponse
    {
        $user = $request->user();

        if ($user->role === UserRole::Admin->value) {
            $this->badges->award($user, $badge);
            return $this->ok();
        }

        if (!$this->badges->claim($user, $badge)) {
            return $this->error('Badge not claimable');
        }

        return $this->ok();
    }

    public function remove(Request $request, string $badge): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== UserRole::Admin->value) {
            return $this->forbidden();
        }

        $this->badges->revoke($user, $badge);

        return $this->ok();
    }
}
