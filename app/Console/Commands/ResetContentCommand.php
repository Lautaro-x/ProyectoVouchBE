<?php

namespace App\Console\Commands;

use Database\Seeders\CategorySeeder;
use Database\Seeders\GenreCategorySeeder;
use Database\Seeders\GenreSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetContentCommand extends Command
{
    protected $signature   = 'db:reset-content {--no-seed : Solo limpiar, sin re-seedear}';
    protected $description = 'Limpia todas las tablas de contenido (mantiene usuarios) y re-ejecuta los seeders';

    private array $tables = [
        'Review_x_Category',
        'ProductScores',
        'Reviews',
        'Product_x_Genre',
        'Product_x_Platform',
        'GameDetails',
        'Products',
        'Platforms',
        'Genre_x_Category',
        'Genres',
        'Categories',
    ];

    public function handle(): int
    {
        if (!$this->confirm('Esto eliminará todos los productos, géneros, categorías, plataformas y reseñas. ¿Continuar?')) {
            $this->info('Operación cancelada.');
            return self::SUCCESS;
        }

        $this->info('Limpiando tablas de contenido...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->tables as $table) {
            DB::table($table)->truncate();
            $this->line("  ✓ {$table}");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        if ($this->option('no-seed')) {
            $this->info('Tablas limpiadas. Seeders omitidos.');
            return self::SUCCESS;
        }

        $this->info('Ejecutando seeders...');
        $this->call(CategorySeeder::class);
        $this->call(GenreSeeder::class);
        $this->call(GenreCategorySeeder::class);

        $this->info('Listo. Puedes importar juegos con: php artisan igdb:import-top --limit=N');

        return self::SUCCESS;
    }
}
