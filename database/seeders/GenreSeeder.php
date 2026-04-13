<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            ['name' => 'RPG',      'slug' => 'rpg',      'igdb_genre_id' => 12],
            ['name' => 'FPS',      'slug' => 'fps',      'igdb_genre_id' => 5],
            ['name' => 'Deporte',  'slug' => 'deporte',  'igdb_genre_id' => 14],
        ];

        foreach ($genres as $genre) {
            Genre::firstOrCreate(['slug' => $genre['slug']], $genre);
        }
    }
}
