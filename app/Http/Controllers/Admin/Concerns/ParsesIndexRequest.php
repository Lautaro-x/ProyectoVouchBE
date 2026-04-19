<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;

trait ParsesIndexRequest
{
    protected function paginationParams(Request $request, array $allowedSorts, string $defaultSort = 'id'): array
    {
        return [
            'sortBy'  => in_array($request->sort_by, $allowedSorts) ? $request->sort_by : $defaultSort,
            'sortDir' => $request->sort_dir === 'desc' ? 'desc' : 'asc',
            'perPage' => min((int) $request->get('per_page', 25), 100),
        ];
    }
}
