<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;

trait ParsesIndexRequest
{
    protected function paginationParams(Request $request, array $allowedSorts, string $defaultSort = 'id'): array
    {
        $sortBy  = $request->input('sort_by');
        $sortDir = $request->input('sort_dir');

        return [
            'sortBy'  => in_array($sortBy, $allowedSorts) ? $sortBy : $defaultSort,
            'sortDir' => $sortDir === 'desc' ? 'desc' : 'asc',
            'perPage' => min((int) $request->input('per_page', 25), 100),
        ];
    }
}
