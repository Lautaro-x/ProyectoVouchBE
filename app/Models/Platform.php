<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Platform extends Model
{
    protected $table = 'Platforms';

    protected $fillable = ['name', 'slug', 'type'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'Product_x_Platform')
            ->withPivot('release_year', 'purchase_url');
    }
}
