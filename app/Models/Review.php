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
    ];

    protected $casts = [
        'weighted_score' => 'integer',
    ];

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
