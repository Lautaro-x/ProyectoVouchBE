<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreCategorySeeder extends Seeder
{
    private array $weights = [
        'rpg' => [
            'gameplay' => 0.25,
            'story'    => 0.30,
            'graphics' => 0.15,
            'sound'    => 0.15,
            'duration' => 0.15,
        ],
        'fps' => [
            'gameplay' => 0.40,
            'story'    => 0.15,
            'graphics' => 0.20,
            'sound'    => 0.15,
            'duration' => 0.10,
        ],
        'sport' => [
            'gameplay' => 0.40,
            'story'    => 0.05,
            'graphics' => 0.20,
            'sound'    => 0.10,
            'duration' => 0.25,
        ],
    ];

    public function run(): void
    {
        $categories = Category::all()->keyBy('slug');

        foreach ($this->weights as $genreSlug => $categoryWeights) {
            $genre = Genre::where('slug', $genreSlug)->first();

            if (!$genre) continue;

            $sync = [];
            foreach ($categoryWeights as $categorySlug => $weight) {
                $category = $categories->get($categorySlug);
                if ($category) {
                    $sync[$category->id] = ['weight' => $weight];
                }
            }

            $genre->categories()->sync($sync);
        }
    }
}
