<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Genre::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $genres = [
            [
                'slug'          => 'rpg',
                'igdb_genre_id' => 12,
                'name'          => ['en' => 'RPG', 'es' => 'RPG', 'fr' => 'RPG', 'pt' => 'RPG', 'it' => 'RPG'],
            ],
            [
                'slug'          => 'fps',
                'igdb_genre_id' => 5,
                'name'          => ['en' => 'FPS', 'es' => 'FPS', 'fr' => 'FPS', 'pt' => 'FPS', 'it' => 'FPS'],
            ],
            [
                'slug'          => 'sport',
                'igdb_genre_id' => 14,
                'name'          => ['en' => 'Sport', 'es' => 'Deporte', 'fr' => 'Sport', 'pt' => 'Esporte', 'it' => 'Sport'],
            ],
        ];

        foreach ($genres as $genre) {
            Genre::create($genre);
        }
    }
}
