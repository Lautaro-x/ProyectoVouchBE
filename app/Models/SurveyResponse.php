<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    protected $fillable = ['survey_id', 'user_id', 'option_id'];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(SurveyOption::class);
    }
}
