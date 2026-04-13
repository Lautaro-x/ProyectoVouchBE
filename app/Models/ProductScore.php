<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductScore extends Model
{
    protected $table = 'ProductScores';

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'product_id';

    protected $fillable = ['product_id', 'global_score', 'pro_score', 'updated_at'];

    protected $casts = [
        'global_score' => 'integer',
        'pro_score'    => 'integer',
        'updated_at'   => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
