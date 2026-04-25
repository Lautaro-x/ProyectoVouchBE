<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function ok(mixed $data = null): JsonResponse
    {
        return response()->json($data ?? ['message' => 'ok']);
    }

    protected function created(mixed $data): JsonResponse
    {
        return response()->json($data, 201);
    }

    protected function error(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    protected function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'Forbidden'], 403);
    }
}
