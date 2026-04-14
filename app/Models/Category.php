<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasTranslations;

    protected $table = 'Categories';

    protected $fillable = ['name', 'slug'];

    public array $translatable = ['name'];

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'name' => $this->getTranslations('name'),
        ]);
    }

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
