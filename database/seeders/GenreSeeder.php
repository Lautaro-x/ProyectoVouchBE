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
                'slug'          => 'point-and-click',
                'igdb_genre_id' => 2,
                'name'          => ['en' => 'Point-and-Click', 'es' => 'Apunta y Clica', 'fr' => 'Pointer-et-Cliquer', 'pt' => 'Apontar e Clicar', 'it' => 'Punta e Clicca'],
            ],
            [
                'slug'          => 'fighting',
                'igdb_genre_id' => 4,
                'name'          => ['en' => 'Fighting', 'es' => 'Lucha', 'fr' => 'Combat', 'pt' => 'Luta', 'it' => 'Combattimento'],
            ],
            [
                'slug'          => 'shooter',
                'igdb_genre_id' => 5,
                'name'          => ['en' => 'Shooter', 'es' => 'Disparos', 'fr' => 'Tir', 'pt' => 'Tiro', 'it' => 'Sparatutto'],
            ],
            [
                'slug'          => 'music',
                'igdb_genre_id' => 7,
                'name'          => ['en' => 'Music', 'es' => 'Música', 'fr' => 'Musique', 'pt' => 'Música', 'it' => 'Musica'],
            ],
            [
                'slug'          => 'platform',
                'igdb_genre_id' => 8,
                'name'          => ['en' => 'Platform', 'es' => 'Plataformas', 'fr' => 'Plateforme', 'pt' => 'Plataforma', 'it' => 'Piattaforme'],
            ],
            [
                'slug'          => 'puzzle',
                'igdb_genre_id' => 9,
                'name'          => ['en' => 'Puzzle', 'es' => 'Puzle', 'fr' => 'Puzzle', 'pt' => 'Quebra-cabeça', 'it' => 'Puzzle'],
            ],
            [
                'slug'          => 'racing',
                'igdb_genre_id' => 10,
                'name'          => ['en' => 'Racing', 'es' => 'Carreras', 'fr' => 'Course', 'pt' => 'Corrida', 'it' => 'Corse'],
            ],
            [
                'slug'          => 'rts',
                'igdb_genre_id' => 11,
                'name'          => ['en' => 'RTS', 'es' => 'Estrategia en Tiempo Real', 'fr' => 'Stratégie en Temps Réel', 'pt' => 'Estratégia em Tempo Real', 'it' => 'Strategia in Tempo Reale'],
            ],
            [
                'slug'          => 'rpg',
                'igdb_genre_id' => 12,
                'name'          => ['en' => 'RPG', 'es' => 'RPG', 'fr' => 'RPG', 'pt' => 'RPG', 'it' => 'RPG'],
            ],
            [
                'slug'          => 'simulator',
                'igdb_genre_id' => 13,
                'name'          => ['en' => 'Simulator', 'es' => 'Simulador', 'fr' => 'Simulateur', 'pt' => 'Simulador', 'it' => 'Simulatore'],
            ],
            [
                'slug'          => 'sport',
                'igdb_genre_id' => 14,
                'name'          => ['en' => 'Sport', 'es' => 'Deporte', 'fr' => 'Sport', 'pt' => 'Esporte', 'it' => 'Sport'],
            ],
            [
                'slug'          => 'strategy',
                'igdb_genre_id' => 15,
                'name'          => ['en' => 'Strategy', 'es' => 'Estrategia', 'fr' => 'Stratégie', 'pt' => 'Estratégia', 'it' => 'Strategia'],
            ],
            [
                'slug'          => 'turn-based-strategy',
                'igdb_genre_id' => 16,
                'name'          => ['en' => 'Turn-Based Strategy', 'es' => 'Estrategia por Turnos', 'fr' => 'Stratégie au Tour par Tour', 'pt' => 'Estratégia por Turnos', 'it' => 'Strategia a Turni'],
            ],
            [
                'slug'          => 'tactical',
                'igdb_genre_id' => 24,
                'name'          => ['en' => 'Tactical', 'es' => 'Táctico', 'fr' => 'Tactique', 'pt' => 'Tático', 'it' => 'Tattico'],
            ],
            [
                'slug'          => 'hack-and-slash',
                'igdb_genre_id' => 25,
                'name'          => ['en' => 'Hack and Slash', 'es' => 'Hack and Slash', 'fr' => 'Hack and Slash', 'pt' => 'Hack and Slash', 'it' => 'Hack and Slash'],
            ],
            [
                'slug'          => 'quiz-trivia',
                'igdb_genre_id' => 26,
                'name'          => ['en' => 'Quiz/Trivia', 'es' => 'Quiz/Trivia', 'fr' => 'Quiz/Trivia', 'pt' => 'Quiz/Trivia', 'it' => 'Quiz/Trivia'],
            ],
            [
                'slug'          => 'pinball',
                'igdb_genre_id' => 30,
                'name'          => ['en' => 'Pinball', 'es' => 'Pinball', 'fr' => 'Flipper', 'pt' => 'Pinball', 'it' => 'Flipper'],
            ],
            [
                'slug'          => 'adventure',
                'igdb_genre_id' => 31,
                'name'          => ['en' => 'Adventure', 'es' => 'Aventura', 'fr' => 'Aventure', 'pt' => 'Aventura', 'it' => 'Avventura'],
            ],
            [
                'slug'          => 'indie',
                'igdb_genre_id' => 32,
                'name'          => ['en' => 'Indie', 'es' => 'Indie', 'fr' => 'Indie', 'pt' => 'Indie', 'it' => 'Indie'],
            ],
            [
                'slug'          => 'arcade',
                'igdb_genre_id' => 33,
                'name'          => ['en' => 'Arcade', 'es' => 'Arcade', 'fr' => 'Arcade', 'pt' => 'Arcade', 'it' => 'Arcade'],
            ],
            [
                'slug'          => 'visual-novel',
                'igdb_genre_id' => 34,
                'name'          => ['en' => 'Visual Novel', 'es' => 'Novela Visual', 'fr' => 'Roman Visuel', 'pt' => 'Romance Visual', 'it' => 'Romanzo Visivo'],
            ],
            [
                'slug'          => 'card-board',
                'igdb_genre_id' => 35,
                'name'          => ['en' => 'Card & Board', 'es' => 'Cartas y Tablero', 'fr' => 'Cartes et Plateau', 'pt' => 'Cartas e Tabuleiro', 'it' => 'Carte e Tavolo'],
            ],
        ];

        foreach ($genres as $genre) {
            Genre::create($genre);
        }
    }
}
