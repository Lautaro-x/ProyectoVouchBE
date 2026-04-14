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

    public function importGame(array $game): ?Product
    {
        if (GameDetail::where('igdb_id', $game['id'])->exists()) {
            return null;
        }

        $product = Product::create([
            'type'        => 'game',
            'title'       => $game['name'],
            'slug'        => $this->uniqueSlug($game['name']),
            'description' => $game['summary'] ?? null,
            'cover_image' => $this->igdb->coverUrl($game['cover'] ?? null),
        ]);

        [$developer, $publisher] = $this->resolveCompanies($game['involved_companies'] ?? []);

        GameDetail::create([
            'product_id' => $product->id,
            'igdb_id'    => $game['id'],
            'developer'  => $developer,
            'publisher'  => $publisher,
        ]);

        $igdbGenreIds = collect($game['genres'] ?? [])->pluck('id')->toArray();
        $genreIds     = Genre::whereIn('igdb_genre_id', $igdbGenreIds)->pluck('id');
        $product->genres()->sync($genreIds);

        $steamUrl     = $this->resolveSteamUrl($game['external_games'] ?? []);
        $releaseDates = collect($game['release_dates'] ?? [])->keyBy('platform.id');

        foreach ($game['platforms'] ?? [] as $igdbPlatform) {
            $platformType = $this->resolvePlatformType($igdbPlatform['name']);

            $platform = Platform::firstOrCreate(
                ['slug' => Str::slug($igdbPlatform['name'])],
                [
                    'name' => $igdbPlatform['name'],
                    'type' => $platformType,
                ]
            );

            $releaseEntry = $releaseDates->get($igdbPlatform['id']);
            $releaseYear  = isset($releaseEntry['date'])
                ? (int) date('Y', $releaseEntry['date'])
                : (isset($game['first_release_date']) ? (int) date('Y', $game['first_release_date']) : null);

            $product->platforms()->syncWithoutDetaching([
                $platform->id => [
                    'release_year' => $releaseYear,
                    'purchase_url' => $platformType === 'pc' ? $steamUrl : null,
                ],
            ]);
        }

        return $product;
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
