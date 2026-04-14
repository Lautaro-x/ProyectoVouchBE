<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreCategorySeeder extends Seeder
{
    private array $weights = [
        'point-and-click'    => ['story' => 0.40, 'gameplay' => 0.25, 'feel' => 0.20, 'graphics' => 0.10, 'sound' => 0.05],
        'fighting'           => ['gameplay' => 0.50, 'feel' => 0.20, 'graphics' => 0.20, 'sound' => 0.10],
        'shooter'            => ['gameplay' => 0.40, 'graphics' => 0.20, 'sound' => 0.15, 'story' => 0.15, 'duration' => 0.10],
        'music'              => ['sound' => 0.45, 'gameplay' => 0.30, 'feel' => 0.15, 'graphics' => 0.10],
        'platform'           => ['gameplay' => 0.45, 'graphics' => 0.20, 'feel' => 0.20, 'sound' => 0.10, 'duration' => 0.05],
        'puzzle'             => ['gameplay' => 0.40, 'feel' => 0.25, 'story' => 0.15, 'graphics' => 0.10, 'sound' => 0.10],
        'racing'             => ['gameplay' => 0.45, 'feel' => 0.25, 'graphics' => 0.20, 'sound' => 0.10],
        'rts'                => ['gameplay' => 0.45, 'duration' => 0.20, 'graphics' => 0.15, 'story' => 0.10, 'sound' => 0.10],
        'rpg'                => ['story' => 0.30, 'gameplay' => 0.25, 'duration' => 0.15, 'graphics' => 0.15, 'sound' => 0.15],
        'simulator'          => ['gameplay' => 0.30, 'feel' => 0.25, 'graphics' => 0.20, 'duration' => 0.15, 'sound' => 0.10],
        'sport'              => ['gameplay' => 0.40, 'duration' => 0.25, 'graphics' => 0.20, 'sound' => 0.10, 'story' => 0.05],
        'strategy'           => ['gameplay' => 0.40, 'duration' => 0.20, 'story' => 0.15, 'graphics' => 0.15, 'sound' => 0.10],
        'turn-based-strategy'=> ['gameplay' => 0.35, 'story' => 0.25, 'duration' => 0.20, 'graphics' => 0.10, 'sound' => 0.10],
        'tactical'           => ['gameplay' => 0.50, 'story' => 0.20, 'graphics' => 0.15, 'sound' => 0.10, 'duration' => 0.05],
        'hack-and-slash'     => ['gameplay' => 0.50, 'feel' => 0.20, 'graphics' => 0.20, 'sound' => 0.10],
        'quiz-trivia'        => ['gameplay' => 0.35, 'story' => 0.25, 'feel' => 0.20, 'sound' => 0.10, 'graphics' => 0.10],
        'pinball'            => ['gameplay' => 0.50, 'feel' => 0.25, 'graphics' => 0.15, 'sound' => 0.10],
        'adventure'          => ['story' => 0.40, 'gameplay' => 0.25, 'graphics' => 0.15, 'feel' => 0.10, 'sound' => 0.10],
        'indie'              => ['gameplay' => 0.25, 'story' => 0.25, 'feel' => 0.20, 'graphics' => 0.15, 'sound' => 0.15],
        'arcade'             => ['gameplay' => 0.50, 'feel' => 0.25, 'graphics' => 0.15, 'sound' => 0.10],
        'visual-novel'       => ['story' => 0.50, 'graphics' => 0.20, 'sound' => 0.15, 'feel' => 0.15],
        'card-board'         => ['gameplay' => 0.50, 'story' => 0.20, 'feel' => 0.15, 'graphics' => 0.10, 'sound' => 0.05],
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
