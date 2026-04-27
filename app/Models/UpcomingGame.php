<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UpcomingGame extends Model
{
    protected $table = 'upcoming_games';

    protected $fillable = [
        'igdb_id',
        'title',
        'slug',
        'release_date',
        'cover_image',
        'trailer_youtube_id',
        'developer',
        'official_url',
        'hypes',
        'is_visible',
        'igdb_synced_at',
    ];

    protected $casts = [
        'release_date'    => 'date',
        'is_visible'      => 'boolean',
        'igdb_synced_at'  => 'datetime',
    ];
}
