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
            'historia' => 0.30,
            'graficos' => 0.15,
            'sonido'   => 0.15,
            'duracion' => 0.15,
        ],
        'fps' => [
            'gameplay' => 0.40,
            'historia' => 0.15,
            'graficos' => 0.20,
            'sonido'   => 0.15,
            'duracion' => 0.10,
        ],
        'deporte' => [
            'gameplay' => 0.40,
            'historia' => 0.05,
            'graficos' => 0.20,
            'sonido'   => 0.10,
            'duracion' => 0.25,
        ],
    ];

    public function run(): void
    {
        $categories = Category::all()->keyBy('slug');

        foreach ($this->weights as $genreSlug => $categoryWeights) {
            $genre = Genre::where('slug', $genreSlug)->first();

            if (!$genre) {
                continue;
            }

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
