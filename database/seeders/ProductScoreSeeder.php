<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductScore;
use Illuminate\Database\Seeder;

class ProductScoreSeeder extends Seeder
{
    public function run(): void
    {
        Product::all()->each(function (Product $product) {
            ProductScore::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'global_score' => rand(72, 94),
                    'pro_score'    => rand(68, 98),
                    'updated_at'   => now(),
                ]
            );
        });
    }
}
