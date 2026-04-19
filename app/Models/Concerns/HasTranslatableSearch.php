<?php

namespace App\Models\Concerns;

trait HasTranslatableSearch
{
    public function scopeSearchTranslatable($query, string $search, string $column = 'name'): void
    {
        $query->where(function ($q) use ($search, $column) {
            foreach (['en', 'es', 'fr', 'pt', 'it'] as $locale) {
                $q->orWhereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.$locale')) LIKE ?",
                    ["%{$search}%"]
                );
            }
        });
    }
}
