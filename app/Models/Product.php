<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class Product extends Model
{
    protected $table = 'Products';

    protected $fillable = [
        'type',
        'title',
        'slug',
        'description',
        'cover_image',
    ];

    public function getCoverImageAttribute(?string $value): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $path = public_path("cover_images/{$this->slug}.{$ext}");
            if (File::exists($path)) {
                return asset("cover_images/{$this->slug}.{$ext}");
            }
        }

        return $value;
    }

    public function uniqueCategories(): Collection
    {
        $categories = collect();
        foreach ($this->genres as $genre) {
            foreach ($genre->categories as $category) {
                if (!$categories->has($category->id)) {
                    $categories->put($category->id, [
                        'id'          => $category->id,
                        'name'        => $category->getTranslations('name'),
                        'description' => $category->getTranslations('description'),
                    ]);
                }
            }
        }
        return $categories->values();
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'Product_x_Genre');
    }

    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'Product_x_Platform')
            ->withPivot('release_date', 'purchase_url');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function score(): HasOne
    {
        return $this->hasOne(ProductScore::class);
    }

    public function gameDetails(): HasOne
    {
        return $this->hasOne(GameDetail::class);
    }
}
