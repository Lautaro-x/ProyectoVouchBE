<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IgdbService
{
    private function token(): string
    {
        return Cache::remember('igdb_access_token', 50 * 24 * 3600, function () {
            $response = Http::post('https://id.twitch.tv/oauth2/token', [
                'client_id'     => config('services.igdb.client_id'),
                'client_secret' => config('services.igdb.client_secret'),
                'grant_type'    => 'client_credentials',
            ]);

            return $response->json('access_token');
        });
    }

    private function query(string $endpoint, string $body): array
    {
        $response = Http::withHeaders([
            'Client-ID'     => config('services.igdb.client_id'),
            'Authorization' => 'Bearer ' . $this->token(),
        ])->withBody($body, 'text/plain')
          ->post("https://api.igdb.com/v4/{$endpoint}");

        return $response->json() ?? [];
    }

    public function search(string $name): array
    {
        $escaped = addslashes($name);

        return $this->query('games',
            "search \"{$escaped}\";
            fields id,name,cover.url,first_release_date,platforms.name,genres.name;
            where version_parent = null
              & (status = null | status = 0 | status = 4)
              & (game_type = 0 | game_type = 4 | game_type = 8 | game_type = 9);
            limit 10;"
        );
    }

    private function fullFields(): string
    {
        return "id,name,summary,storyline,cover.url,
            first_release_date,
            rating,rating_count,
            aggregated_rating,aggregated_rating_count,
            hypes,follows,status,game_type,
            involved_companies.company.name,involved_companies.developer,involved_companies.publisher,
            platforms.name,platforms.abbreviation,
            release_dates.date,release_dates.platform.id,
            genres.name,
            game_modes.name,
            themes.name,
            player_perspectives.name,
            franchises.name,
            videos.name,videos.video_id,
            screenshots.url,
            age_ratings.category,age_ratings.rating,
            websites.category,websites.url,
            external_games.uid,external_games.url";
    }

    public function find(int $igdbId): ?array
    {
        $results = $this->query('games',
            "fields {$this->fullFields()};
            where id = {$igdbId};"
        );

        return $results[0] ?? null;
    }

    public function topByGenre(int $igdbGenreId, int $limit = 10): array
    {
        return $this->query('games',
            "fields {$this->fullFields()};
            where genres = ({$igdbGenreId})
              & aggregated_rating_count > 5
              & cover != null
              & version_parent = null
              & (status = null | status = 0 | status = 4)
              & (game_type = 0 | game_type = 4 | game_type = 8 | game_type = 9);
            sort aggregated_rating desc;
            limit {$limit};"
        );
    }

    public function recentGames(int $hours = 48): array
    {
        $since = time() - ($hours * 3600);
        $now   = time();

        return $this->query('games',
            "fields {$this->fullFields()};
            where first_release_date >= {$since}
              & first_release_date <= {$now}
              & cover != null
              & version_parent = null
              & (status = null | status = 0 | status = 4)
              & (game_type = 0 | game_type = 4 | game_type = 8 | game_type = 9);
            sort first_release_date desc;
            limit 50;"
        );
    }

    public function coverUrl(?array $cover): ?string
    {
        if (!$cover || !isset($cover['url'])) {
            return null;
        }

        return 'https:' . str_replace('t_thumb', 't_cover_big', $cover['url']);
    }
}
