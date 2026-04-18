<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Announcement extends Model
{
    use HasTranslations;

    public array $translatable = ['title', 'body'];

    protected $fillable = ['title', 'body', 'starts_at', 'ends_at'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function hasAllTranslations(): bool
    {
        foreach (['es', 'en', 'fr', 'pt', 'it'] as $lang) {
            if (empty($this->getTranslation('title', $lang, false))) return false;
            if (empty($this->getTranslation('body',  $lang, false))) return false;
        }
        return true;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'title' => $this->getTranslations('title'),
            'body'  => $this->getTranslations('body'),
        ]);
    }
}
