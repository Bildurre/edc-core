<?php

namespace Edc\Core\Support\Concerns;

use Edc\Core\Support\SqlFold;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtros de listado declarativos. La entidad declara las columnas buscables en
 * la propiedad `$searchable`. Entiende `search` (texto) y `status`
 * (published|draft|trashed). El formato casa con el FiltersBar del admin-kit.
 */
trait HasFilters
{
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $columns = property_exists($this, 'searchable') ? $this->searchable : [];
            $locale = app()->getLocale();
            // Agrupado para no romper otros wheres del listado (status, etc.).
            $query->where(function (Builder $query) use ($columns, $search, $locale) {
                // Plegado a lo humano en AMBOS lados ("CaMiON" = "camión"):
                // los json traducibles comparan en binario y el lower() de
                // SQLite solo baja ASCII — ver SqlFold.
                $grammar = $query->getQuery()->getGrammar();
                $term = '%'.SqlFold::term($search).'%';
                foreach ($columns as $column) {
                    // Columna traducible: busca SOLO en el json del locale
                    // activo (antes el LIKE sobre el json crudo mezclaba locales).
                    $target = method_exists($this, 'isTranslatableAttribute')
                        && $this->isTranslatableAttribute($column)
                            ? "{$column}->{$locale}"
                            : $column;
                    $query->orWhereRaw(SqlFold::expression($grammar->wrap($target)).' like ?', [$term]);
                }
            });
        }

        $status = $filters['status'] ?? null;
        if ($status === 'published') {
            $query->where('is_published', true);
        } elseif ($status === 'draft') {
            $query->where('is_published', false);
        } elseif ($status === 'trashed') {
            $query->onlyTrashed();
        }

        return $query;
    }
}
