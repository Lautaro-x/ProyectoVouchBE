<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Category::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $categories = [
            [
                'slug' => 'gameplay',
                'name' => ['en' => 'Gameplay', 'es' => 'Jugabilidad', 'fr' => 'Jouabilité', 'pt' => 'Jogabilidade', 'it' => 'Giocabilità'],
            ],
            [
                'slug' => 'story',
                'name' => ['en' => 'Story', 'es' => 'Historia', 'fr' => 'Histoire', 'pt' => 'História', 'it' => 'Storia'],
            ],
            [
                'slug' => 'graphics',
                'name' => ['en' => 'Graphics', 'es' => 'Gráficos', 'fr' => 'Graphismes', 'pt' => 'Gráficos', 'it' => 'Grafica'],
            ],
            [
                'slug' => 'sound',
                'name' => ['en' => 'Sound', 'es' => 'Sonido', 'fr' => 'Son', 'pt' => 'Som', 'it' => 'Sonoro'],
            ],
            [
                'slug' => 'duration',
                'name' => ['en' => 'Duration', 'es' => 'Duración', 'fr' => 'Durée', 'pt' => 'Duração', 'it' => 'Durata'],
            ],
            [
                'slug' => 'feel',
                'name' => ['en' => 'Feel', 'es' => 'Sensación', 'fr' => 'Ressenti', 'pt' => 'Sensação', 'it' => 'Sensazione'],
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
