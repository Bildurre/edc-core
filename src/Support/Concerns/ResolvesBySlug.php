<?php

namespace Bgm\Core\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Resolución por slug traducible: busca el slug en CUALQUIER locale.
 * (DC-11: más adelante se respaldará con columna generada + índice único.)
 */
trait ResolvesBySlug
{
    public function scopeWhereSlug(Builder $query, string $slug, string $column = 'slug'): Builder
    {
        $locales = array_keys(config('motor.locales', []));

        return $query->where(function (Builder $query) use ($slug, $locales, $column) {
            foreach ($locales as $locale) {
                $query->orWhere("{$column}->{$locale}", $slug);
            }
        });
    }
}
