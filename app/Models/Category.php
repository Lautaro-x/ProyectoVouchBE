<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'Categories';

    protected $fillable = ['name', 'slug'];

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'Genre_x_Category')
            ->withPivot('weight')
            ->withTimestamps();
    }

    public function reviewScores(): HasMany
    {
        return $this->hasMany(ReviewScore::class);
    }
}
