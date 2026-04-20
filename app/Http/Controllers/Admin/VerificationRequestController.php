<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');

        $requests = VerificationRequest::with('user:id,name,email,role,badges')
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => $this->format($r));

        return response()->json($requests);
    }

    public function approve(Request $request, VerificationRequest $verificationRequest): JsonResponse
    {
        if ($verificationRequest->status !== 'pending') {
            return response()->json(['message' => 'not_pending'], 422);
        }

        $user   = $verificationRequest->user;
        $badges = $user->badges ?? [];

        if (!in_array('verificado', $badges)) {
            $badges[] = 'verificado';
        }

        $user->badges = $badges;

        if ($verificationRequest->type === 'press' && $user->role === 'user') {
            $user->role = 'critic';
        }

        $user->save();

        $verificationRequest->update([
            'status'      => 'approved',
            'reviewed_at' => now(),
            'admin_note'  => $request->input('admin_note'),
        ]);

        return response()->json($this->format($verificationRequest->fresh('user')));
    }

    public function reject(Request $request, VerificationRequest $verificationRequest): JsonResponse
    {
        if ($verificationRequest->status !== 'pending') {
            return response()->json(['message' => 'not_pending'], 422);
        }

        $verificationRequest->update([
            'status'      => 'rejected',
            'reviewed_at' => now(),
            'admin_note'  => $request->input('admin_note'),
        ]);

        return response()->json($this->format($verificationRequest->fresh('user')));
    }

    private function format(VerificationRequest $r): array
    {
        return [
            'id'              => $r->id,
            'type'            => $r->type,
            'social_network'  => $r->social_network,
            'social_username' => $r->social_username,
            'press_url'       => $r->press_url,
            'press_contact'   => $r->press_contact,
            'status'          => $r->status,
            'admin_note'      => $r->admin_note,
            'reviewed_at'     => $r->reviewed_at?->toDateTimeString(),
            'created_at'      => $r->created_at->toDateTimeString(),
            'user'            => [
                'id'     => $r->user->id,
                'name'   => $r->user->name,
                'email'  => $r->user->email,
                'role'   => $r->user->role,
                'badges' => $r->user->badges ?? [],
            ],
        ];
    }
}
