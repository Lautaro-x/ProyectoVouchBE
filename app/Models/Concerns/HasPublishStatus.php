<?php

namespace App\Models\Concerns;

trait HasPublishStatus
{
    public function status(): string
    {
        if (!$this->hasAllTranslations()) return 'missing_translations';
        $now = now();
        if ($now->lt($this->starts_at)) return 'upcoming';
        if ($now->gt($this->ends_at))   return 'ended';
        return 'active';
    }
}
