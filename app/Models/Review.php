<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    protected $table = 'Reviews';

    protected $fillable = [
        'user_id',
        'product_id',
        'body',
        'weighted_score',
        'letter_grade',
        'banned_at',
        'ban_reason',
    ];

    protected $casts = [
        'weighted_score' => 'integer',
        'banned_at'      => 'datetime',
    ];

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ReviewScore::class);
    }
}
