<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatableSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class Genre extends Model
{
    use HasFactory, HasTranslations, HasTranslatableSearch;

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

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'Product_x_Genre');
    }
}
