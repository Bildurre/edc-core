<?php

namespace Edc\Core\Previews\Http\Controllers;

use Edc\Core\Previews\CatalogItem;
use Edc\Core\Previews\PreviewRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Catálogo público genérico: listado de cualquier entidad del registry de
 * previews. Solo lectura, solo publicadas (si el modelo distingue estado),
 * locale por SetLocale. Dos modos:
 *
 * - lista (por defecto): ?page, ?per_page (24, tope 48), ?search (LIKE sobre
 *   el name del locale activo), ?sort (`name` asc / `name_desc` sobre el name
 *   del locale activo; `oldest` = id asc; default/`latest` = id desc), con
 *   meta de paginación.
 * - random: ?mode=random&count=N (1..12, default 4), sin paginar; ignora ?sort.
 *
 * ?exclude=<id> deja fuera una entidad (los singles excluyen la actual).
 */
class CatalogController extends Controller
{
    public function __construct(protected PreviewRegistry $registry) {}

    public function show(Request $request, string $key): JsonResponse
    {
        abort_unless($this->registry->has($key), 404);

        $locale = app()->getLocale();
        $query = CatalogItem::query($this->registry->modelFor($key));

        if (($exclude = (int) $request->query('exclude')) > 0) {
            $query->whereKeyNot($exclude);
        }

        if ($request->query('mode') === 'random') {
            $count = min(max((int) $request->query('count', 4), 1), 12);

            $items = $query->inRandomOrder()->limit($count)->get()
                ->map(fn ($model) => CatalogItem::fromModel($model, $key, $locale))
                ->values()
                ->all();

            return response()->json(['key' => $key, 'data' => $items]);
        }

        if (($search = trim((string) $request->query('search'))) !== '') {
            $query->where("name->{$locale}", 'like', "%{$search}%");
        }

        match ($request->query('sort')) {
            'name' => $query->orderBy("name->{$locale}"),
            'name_desc' => $query->orderByDesc("name->{$locale}"),
            'oldest' => $query->orderBy('id'),
            default => $query->orderByDesc('id'),
        };

        $perPage = min(max((int) $request->query('per_page', 24), 1), 48);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'key' => $key,
            'data' => $paginated->getCollection()
                ->map(fn ($model) => CatalogItem::fromModel($model, $key, $locale))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }
}
