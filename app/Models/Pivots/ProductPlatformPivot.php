<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductPlatformPivot extends Pivot
{
    protected $casts = [
        'purchase_url' => 'array',
    ];
}
