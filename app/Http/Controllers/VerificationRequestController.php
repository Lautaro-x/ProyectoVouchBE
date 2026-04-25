<?php

namespace App\Http\Controllers;

use App\Models\VerificationRequest;
use App\Rules\HttpsUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationRequestController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $latest = VerificationRequest::where('user_id', $request->user()->id)
            ->latest()
            ->first();

        return response()->json($latest ? $this->format($latest) : null);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'            => 'required|in:verified,press',
            'social_network'  => 'required_if:type,verified|nullable|string|max:50',
            'social_username' => 'required_if:type,verified|nullable|string|max:100',
            'press_url'       => ['required_if:type,press', 'nullable', new HttpsUrl(), 'max:500'],
            'press_contact'   => 'required_if:type,press|nullable|string|max:150',
        ]);

        $pending = VerificationRequest::where('user_id', $request->user()->id)
            ->where('type', $data['type'])
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            return response()->json(['message' => 'already_pending'], 422);
        }

        $req = VerificationRequest::create([
            'user_id'         => $request->user()->id,
            'type'            => $data['type'],
            'social_network'  => $data['social_network'] ?? null,
            'social_username' => $data['social_username'] ?? null,
            'press_url'       => $data['press_url'] ?? null,
            'press_contact'   => $data['press_contact'] ?? null,
        ]);

        return response()->json($this->format($req), 201);
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
            'created_at'      => $r->created_at->toDateTimeString(),
        ];
    }
}
