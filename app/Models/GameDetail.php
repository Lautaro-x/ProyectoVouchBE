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

    protected $fillable = [
        'product_id', 'igdb_id', 'igdb_synced_at', 'developer', 'publisher',
        'storyline', 'igdb_rating', 'igdb_rating_count',
        'aggregated_rating', 'aggregated_rating_count',
        'hypes', 'follows', 'status', 'category',
        'franchise', 'trailer_youtube_id', 'pegi_rating', 'esrb_rating',
        'official_url',
        'game_modes', 'themes', 'player_perspectives', 'screenshots',
    ];

    protected $casts = [
        'igdb_synced_at'     => 'datetime',
        'game_modes'         => 'array',
        'themes'             => 'array',
        'player_perspectives'=> 'array',
        'screenshots'        => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
