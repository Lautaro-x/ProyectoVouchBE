<?php

namespace App\Services;

use App\Models\GameDetail;
use App\Models\Platform;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductImportService
{
    public function __construct(private IgdbService $igdb) {}

    public function importGame(array $game, int $genreId): ?Product
    {
        if (GameDetail::where('igdb_id', $game['id'])->exists()) {
            return null;
        }

        $product = Product::create([
            'type'        => 'game',
            'genre_id'    => $genreId,
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

        $releaseDates = collect($game['release_dates'] ?? [])->keyBy('platform.id');

        foreach ($game['platforms'] ?? [] as $igdbPlatform) {
            $platform = Platform::firstOrCreate(
                ['slug' => Str::slug($igdbPlatform['name'])],
                [
                    'name' => $igdbPlatform['name'],
                    'type' => $this->resolvePlatformType($igdbPlatform['name']),
                ]
            );

            $releaseEntry = $releaseDates->get($igdbPlatform['id']);
            $releaseYear  = $releaseEntry
                ? (int) date('Y', $releaseEntry['date'])
                : (isset($game['first_release_date']) ? (int) date('Y', $game['first_release_date']) : null);

            $product->platforms()->syncWithoutDetaching([
                $platform->id => ['release_year' => $releaseYear],
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
