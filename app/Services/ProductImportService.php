<?php

namespace App\Services;

use App\Models\GameDetail;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductImportService
{
    public function __construct(private IgdbService $igdb) {}

    public function importGame(array $game): Product
    {
        $existingDetail = GameDetail::with('product')->where('igdb_id', $game['id'])->first();
        $detailData     = $this->buildDetailData($game);

        if ($existingDetail) {
            $product = $existingDetail->product;
            $product->update([
                'description' => $game['summary'] ?? null,
                'cover_image' => $this->igdb->coverUrl($game['cover'] ?? null),
            ]);
            $existingDetail->update($detailData);
        } else {
            $product = Product::create([
                'type'        => 'game',
                'title'       => $game['name'],
                'slug'        => $this->uniqueSlug($game['name']),
                'description' => $game['summary'] ?? null,
                'cover_image' => $this->igdb->coverUrl($game['cover'] ?? null),
            ]);

            GameDetail::create(array_merge(
                ['product_id' => $product->id, 'igdb_id' => $game['id']],
                $detailData
            ));
        }

        $this->syncGenres($product, $game);
        $this->syncPlatforms($product, $game);

        return $product;
    }

    private function buildDetailData(array $game): array
    {
        [$developer, $publisher] = $this->resolveCompanies($game['involved_companies'] ?? []);
        [$pegiRating, $esrbRating] = $this->resolveAgeRatings($game['age_ratings'] ?? []);
        [$gogUrl, $epicUrl] = $this->resolveStoreUrls($game['external_games'] ?? []);

        return [
            'developer'               => $developer,
            'publisher'               => $publisher,
            'storyline'               => $game['storyline'] ?? null,
            'igdb_rating'             => isset($game['rating']) ? round($game['rating'] / 10, 2) : null,
            'igdb_rating_count'       => $game['rating_count'] ?? null,
            'aggregated_rating'       => isset($game['aggregated_rating']) ? round($game['aggregated_rating'] / 10, 2) : null,
            'aggregated_rating_count' => $game['aggregated_rating_count'] ?? null,
            'hypes'                   => $game['hypes'] ?? null,
            'follows'                 => $game['follows'] ?? null,
            'status'                  => $game['status'] ?? null,
            'category'                => $game['category'] ?? null,
            'franchise'               => $game['franchises'][0]['name'] ?? null,
            'trailer_youtube_id'      => $this->resolveTrailer($game['videos'] ?? []),
            'pegi_rating'             => $pegiRating,
            'esrb_rating'             => $esrbRating,
            'gog_url'                 => $gogUrl,
            'epic_url'                => $epicUrl,
            'official_url'            => $this->resolveOfficialUrl($game['websites'] ?? []),
            'game_modes'              => collect($game['game_modes'] ?? [])->pluck('name')->values()->all() ?: null,
            'themes'                  => collect($game['themes'] ?? [])->pluck('name')->values()->all() ?: null,
            'player_perspectives'     => collect($game['player_perspectives'] ?? [])->pluck('name')->values()->all() ?: null,
            'screenshots'             => $this->resolveScreenshots($game['screenshots'] ?? []),
        ];
    }

    private function syncGenres(Product $product, array $game): void
    {
        $igdbGenreIds = collect($game['genres'] ?? [])->pluck('id')->toArray();
        $genreIds     = Genre::whereIn('igdb_genre_id', $igdbGenreIds)->pluck('id');
        $product->genres()->sync($genreIds);
    }

    private function syncPlatforms(Product $product, array $game): void
    {
        $steamUrl     = $this->resolveSteamUrl($game['external_games'] ?? []);
        $releaseDates = collect($game['release_dates'] ?? [])->keyBy('platform.id');

        foreach ($game['platforms'] ?? [] as $igdbPlatform) {
            $platformType = $this->resolvePlatformType($igdbPlatform['name']);

            $platform = Platform::firstOrCreate(
                ['slug' => Str::slug($igdbPlatform['name'])],
                ['name' => $igdbPlatform['name'], 'type' => $platformType]
            );

            $releaseEntry = $releaseDates->get($igdbPlatform['id']);
            $releaseDate  = isset($releaseEntry['date'])
                ? date('Y-m-d', $releaseEntry['date'])
                : (isset($game['first_release_date']) ? date('Y-m-d', $game['first_release_date']) : null);

            $product->platforms()->syncWithoutDetaching([
                $platform->id => [
                    'release_date' => $releaseDate,
                    'purchase_url' => $platformType === 'pc' ? $steamUrl : null,
                ],
            ]);
        }
    }

    private function uniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $count = Product::where('slug', 'like', "{$slug}%")->count();

        return $count > 0 ? "{$slug}-{$count}" : $slug;
    }

    private function resolveCompanies(array $companies): array
    {
        $developer = null;
        $publisher  = null;

        foreach ($companies as $entry) {
            if (!empty($entry['developer']) && !$developer) {
                $developer = $entry['company']['name'];
            }
            if (!empty($entry['publisher']) && !$publisher) {
                $publisher = $entry['company']['name'];
            }
        }

        return [$developer, $publisher];
    }

    private function resolveSteamUrl(array $externalGames): ?string
    {
        foreach ($externalGames as $ext) {
            if (($ext['category'] ?? null) === 1 && !empty($ext['uid'])) {
                return "https://store.steampowered.com/app/{$ext['uid']}/";
            }
        }

        return null;
    }

    private function resolveStoreUrls(array $externalGames): array
    {
        $gog  = null;
        $epic = null;

        foreach ($externalGames as $ext) {
            $cat = $ext['category'] ?? null;
            $uid = $ext['uid'] ?? null;

            if (!$uid) continue;

            if ($cat === 5 && !$gog) {
                $gog = "https://www.gog.com/en/game/{$uid}";
            } elseif ($cat === 26 && !$epic) {
                $epic = "https://store.epicgames.com/p/{$uid}";
            }
        }

        return [$gog, $epic];
    }

    private function resolveOfficialUrl(array $websites): ?string
    {
        foreach ($websites as $site) {
            if (($site['category'] ?? null) === 1 && !empty($site['url'])) {
                return $site['url'];
            }
        }

        return null;
    }

    private function resolveTrailer(array $videos): ?string
    {
        foreach ($videos as $video) {
            if (!empty($video['video_id'])) {
                return $video['video_id'];
            }
        }

        return null;
    }

    private function resolveAgeRatings(array $ageRatings): array
    {
        $pegiMap = [1 => '3', 2 => '7', 3 => '12', 4 => '16', 5 => '18'];
        $esrbMap = [1 => 'RP', 2 => 'EC', 3 => 'E', 4 => 'E10+', 5 => 'T', 6 => 'M', 7 => 'AO'];

        $pegi = null;
        $esrb = null;

        foreach ($ageRatings as $rating) {
            $cat = $rating['category'] ?? null;
            $val = $rating['rating'] ?? null;

            if ($cat === 2 && isset($pegiMap[$val])) {
                $pegi = 'PEGI ' . $pegiMap[$val];
            } elseif ($cat === 1 && isset($esrbMap[$val])) {
                $esrb = $esrbMap[$val];
            }
        }

        return [$pegi, $esrb];
    }

    private function resolveScreenshots(array $screenshots): ?array
    {
        $urls = [];

        foreach (array_slice($screenshots, 0, 10) as $shot) {
            if (!empty($shot['url'])) {
                $urls[] = 'https:' . str_replace('t_thumb', 't_screenshot_big', $shot['url']);
            }
        }

        return $urls ?: null;
    }

    private function resolvePlatformType(string $name): string
    {
        foreach (['PC', 'Mac', 'Linux', 'Windows'] as $keyword) {
            if (stripos($name, $keyword) !== false) {
                return 'pc';
            }
        }

        return 'console';
    }
}
