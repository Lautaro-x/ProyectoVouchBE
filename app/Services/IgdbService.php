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
            limit 10;"
        );
    }

    public function find(int $igdbId): ?array
    {
        $results = $this->query('games',
            "fields id,name,summary,cover.url,first_release_date,
            involved_companies.company.name,involved_companies.developer,involved_companies.publisher,
            platforms.name,platforms.abbreviation,
            release_dates.date,release_dates.platform.id,
            genres.name;
            where id = {$igdbId};"
        );

        return $results[0] ?? null;
    }

    public function topByGenre(int $igdbGenreId, int $limit = 10): array
    {
        return $this->query('games',
            "fields id,name,summary,cover.url,first_release_date,
            involved_companies.company.name,involved_companies.developer,involved_companies.publisher,
            platforms.name,platforms.abbreviation,
            release_dates.date,release_dates.platform.id,
            genres.name;
            where genres = ({$igdbGenreId})
              & aggregated_rating_count > 5
              & cover != null;
            sort aggregated_rating desc;
            limit {$limit};"
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
