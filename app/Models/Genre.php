<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Genre extends Model
{
    protected $table = 'Genres';

    protected $fillable = ['name', 'slug', 'igdb_genre_id'];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'Genre_x_Category')
            ->withPivot('weight')
            ->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
