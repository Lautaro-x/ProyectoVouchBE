<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class SurveyOption extends Model
{
    use HasTranslations;

    public array $translatable = ['text'];

    protected $fillable = ['survey_id', 'text', 'order'];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class, 'option_id');
    }

    public function hasAllTranslations(): bool
    {
        foreach (['es', 'en', 'fr', 'pt', 'it'] as $lang) {
            if (empty($this->getTranslation('text', $lang, false))) return false;
        }
        return true;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'text' => $this->getTranslations('text'),
        ]);
    }
}
