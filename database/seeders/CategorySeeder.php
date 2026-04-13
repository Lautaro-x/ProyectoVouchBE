<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Gameplay',  'slug' => 'gameplay'],
            ['name' => 'Historia',  'slug' => 'historia'],
            ['name' => 'Gráficos',  'slug' => 'graficos'],
            ['name' => 'Sonido',    'slug' => 'sonido'],
            ['name' => 'Duración',  'slug' => 'duracion'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
