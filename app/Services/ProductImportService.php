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
            'category'                => $game['game_type'] ?? null,
            'franchise'               => $game['franchises'][0]['name'] ?? null,
            'trailer_youtube_id'      => $this->resolveTrailer($game['videos'] ?? []),
            'pegi_rating'             => $pegiRating,
            'esrb_rating'             => $esrbRating,
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
        $storeMap     = $this->buildStoreUrlMap($game['external_games'] ?? []);
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

            $links = $this->resolvePlatformLinks($igdbPlatform['name'], $platformType, $storeMap);

            $product->platforms()->syncWithoutDetaching([
                $platform->id => [
                    'release_date' => $releaseDate,
                    'purchase_url' => $links,
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

    private function buildStoreUrlMap(array $externalGames): array
    {
        $map = [];

        foreach ($externalGames as $ext) {
            $url = $ext['url'] ?? null;
            if (!$url) continue;

            match (true) {
                str_contains($url, 'store.steampowered.com')  => $map['steam']    ??= $url,
                str_contains($url, 'gog.com')                 => $map['gog']      ??= $url,
                str_contains($url, 'epicgames.com')           => $map['epic']     ??= $url,
                str_contains($url, 'xbox.com') ||
                str_contains($url, 'microsoft.com/store')     => $map['xbox']     ??= $url,
                str_contains($url, 'nintendo.com')            => $map['eshop']    ??= $url,
                str_contains($url, 'store.playstation.com')   => $map['ps_store'] ??= $url,
                default                                       => null,
            };
        }

        return array_filter($map);
    }

    private function resolvePlatformLinks(string $platformName, string $platformType, array $storeMap): ?array
    {
        $name = strtolower($platformName);

        $keys = match (true) {
            str_contains($name, 'playstation') => ['ps_store'],
            str_contains($name, 'xbox')        => ['xbox'],
            str_contains($name, 'switch')      => ['eshop'],
            $platformType === 'pc'             => ['steam', 'gog', 'epic'],
            default                            => [],
        };

        $links = array_filter(
            array_intersect_key($storeMap, array_flip($keys))
        );

        return $links ?: null;
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
