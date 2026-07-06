<?php

namespace Edc\Core\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Estado publicado/borrador para una entidad. Requiere la columna `is_published`.
 */
trait HasPublishedState
{
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('is_published', false);
    }

    public function togglePublished(): bool
    {
        $this->is_published = ! $this->is_published;
        $this->save();

        return $this->is_published;
    }
}
