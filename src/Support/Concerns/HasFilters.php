<?php

namespace Bgm\Core\Support\Concerns;

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
            $query->where(function (Builder $query) use ($columns, $search) {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
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
