<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameDetail extends Model
{
    protected $table = 'GameDetails';

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'product_id';

    protected $fillable = ['product_id', 'igdb_id', 'developer', 'publisher'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
