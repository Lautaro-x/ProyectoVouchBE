<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Genre extends Model
{
    use HasTranslations;

    protected $table = 'Genres';

    protected $fillable = ['name', 'slug', 'igdb_genre_id'];

    public array $translatable = ['name'];

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'name' => $this->getTranslations('name'),
        ]);
    }

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
