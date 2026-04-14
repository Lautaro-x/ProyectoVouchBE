<?php

namespace App\Console\Commands;

use App\Models\Genre;
use App\Services\IgdbService;
use App\Services\ProductImportService;
use Illuminate\Console\Command;

class IgdbImportTopCommand extends Command
{
    protected $signature   = 'igdb:import-top {--limit=10 : Juegos por género}';
    protected $description = 'Importa los juegos más valorados de IGDB para cada género configurado';

    public function __construct(
        private IgdbService $igdb,
        private ProductImportService $importer
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit  = (int) $this->option('limit');
        $genres = Genre::whereNotNull('igdb_genre_id')->get();

        if ($genres->isEmpty()) {
            $this->error('No hay géneros con igdb_genre_id. Ejecuta: php artisan db:seed');
            return self::FAILURE;
        }

        foreach ($genres as $genre) {
            $this->info("Importando top {$limit} de {$genre->name}...");

            $games = $this->igdb->topByGenre($genre->igdb_genre_id, $limit);

            if (empty($games)) {
                $this->warn("  Sin resultados para {$genre->name}");
                continue;
            }

            $imported = 0;
            $skipped  = 0;

            foreach ($games as $game) {
                $product = $this->importer->importGame($game);

                if ($product) {
                    $this->line("  ✓ {$product->title}");
                    $imported++;
                } else {
                    $this->line("  - {$game['name']} (ya existe)");
                    $skipped++;
                }
            }

            $this->info("  Importados: {$imported} | Omitidos: {$skipped}");
        }

        $this->info('Importación completada.');
        return self::SUCCESS;
    }
}
