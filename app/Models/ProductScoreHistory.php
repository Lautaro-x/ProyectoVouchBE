<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductScoreHistory extends Model
{
    protected $table = 'ProductScoreHistory';

    public $timestamps = false;

    protected $fillable = ['product_id', 'global_score', 'pro_score', 'snapshot_date'];

    protected $casts = [
        'global_score'  => 'integer',
        'pro_score'     => 'integer',
        'snapshot_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
