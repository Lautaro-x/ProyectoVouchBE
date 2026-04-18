<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Survey extends Model
{
    use HasTranslations;

    public array $translatable = ['title', 'question'];

    protected $fillable = ['title', 'question', 'starts_at', 'ends_at'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(SurveyOption::class)->orderBy('order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function hasAllTranslations(): bool
    {
        $langs = ['es', 'en', 'fr', 'pt', 'it'];
        foreach ($langs as $lang) {
            if (empty($this->getTranslation('title', $lang, false))) return false;
            if (empty($this->getTranslation('question', $lang, false))) return false;
        }
        foreach ($this->options as $option) {
            if (!$option->hasAllTranslations()) return false;
        }
        return true;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'title'    => $this->getTranslations('title'),
            'question' => $this->getTranslations('question'),
        ]);
    }
}
