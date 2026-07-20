<?php

namespace Edc\Core\Menu;

use Edc\Core\Content\Models\Page;
use Edc\Core\Menu\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD del menú configurable (doc 10 ampliado) + construcción del árbol para
 * el admin (todo, con la página embebida) y para el público (solo visibles,
 * páginas además publicadas). Sin grupos: la jerarquía SIEMPRE se deriva de
 * `pages.parent_id` (una página con hijas hace de desplegable) — nunca se
 * copia a `menu_items`. Un item de tipo 'route' sí puede colgar de una
 * página raíz vía `menu_items.parent_id` (las páginas lo dejan siempre a
 * null). Un solo nivel de anidado.
 */
class MenuService
{
    public function __construct(protected MenuSync $sync) {}

    public function sync(): void
    {
        $this->sync->sync();
    }

    /** Árbol completo (visibles u ocultos): lo pinta el admin. */
    public function adminTree(): array
    {
        $items = MenuItem::query()->with('page')->orderBy('order')->get();
        $parents = $this->effectiveParents($items);

        return $this->buildTree($items, $parents, null, fn (MenuItem $item) => $this->node($item));
    }

    /** Árbol público: solo visibles; páginas además publicadas. */
    public function publicTree(): array
    {
        $items = MenuItem::query()->with('page')->orderBy('order')->get();
        $parents = $this->effectiveParents($items);

        return $this->buildTree($items, $parents, null, fn (MenuItem $item) => $this->publicNode($item), keep: true);
    }

    /**
     * Guarda TODO el árbol de una vez (nada se persiste hasta pulsar
     * Guardar en el admin): `$items` es la lista COMPLETA, en el orden del
     * menú (padre seguido de sus hijas), cada una con su `parent_id`
     * semántico DE DESTINO (no el que ya tuviera en base de datos) y su
     * visibilidad. Escribe `menu_items` (order/is_visible siempre;
     * `parent_id` solo si es una ruta) y `pages` (parent_id + order de las
     * hijas, para que el CRM y el nav legado cuadren) en una transacción.
     */
    public function replaceTree(array $items): array
    {
        // Todos los items (no solo los enviados): hace falta para resolver
        // tipo/página de un padre que no esté en `$items` y para calcular la
        // jerarquía de DESTINO completa aunque la petición sea parcial.
        $allItems = MenuItem::query()->with('page')->get();
        $records = $allItems->keyBy('id');

        $ids = collect($items)->pluck('id');
        if ($records->only($ids->all())->count() !== $ids->unique()->count()) {
            throw ValidationException::withMessages(['items' => 'Algún elemento no existe.']);
        }

        // Padre EFECTIVO de destino para cada item: el actual (derivado de la
        // base de datos), con los enviados SOBRESCRITOS por su parent_id de
        // destino. Así se valida la jerarquía completa resultante (se puede
        // sacar una página a la raíz y anidar otra bajo ella en la misma
        // petición) sin depender de que el payload incluya TODO el árbol.
        $requestedParent = $this->effectiveParents($allItems);
        foreach ($items as $entry) {
            $requestedParent[$entry['id']] = $entry['parent_id'] ?? null;
        }

        foreach ($items as $entry) {
            $parentId = $entry['parent_id'] ?? null;
            if ($parentId === null) {
                continue;
            }
            $parent = $records->get($parentId);
            if (! $parent || $parent->type !== 'page') {
                throw ValidationException::withMessages(['items' => 'El padre debe ser una página.']);
            }
            if (($requestedParent[$parentId] ?? null) !== null) {
                throw ValidationException::withMessages(['items' => 'Solo se admite un nivel de anidado.']);
            }
        }

        DB::transaction(function () use ($items, $records) {
            // Orden dentro de cada nivel (parent_id semántico), en el orden
            // de aparición del array; y aparte, orden SOLO entre las hijas
            // página de una misma madre (para pages.order).
            $order = [];
            $pageOrder = [];

            foreach ($items as $entry) {
                $item = $records->get($entry['id']);
                $parentId = $entry['parent_id'] ?? null;
                $key = $parentId ?? 'root';
                $order[$key] = ($order[$key] ?? -1) + 1;

                $item->is_visible = (bool) ($entry['is_visible'] ?? true);
                $item->order = $order[$key];
                // Las páginas nunca cuelgan de otro item en menu_items (su
                // jerarquía vive en pages.parent_id); las rutas sí.
                $item->parent_id = $item->type === 'route' ? $parentId : null;
                $item->save();

                if ($item->type === 'page' && $item->page_id) {
                    $parentPageId = $parentId ? $records->get($parentId)->page_id : null;
                    $pageOrder[$key] = ($pageOrder[$key] ?? -1) + 1;
                    Page::query()->whereKey($item->page_id)->update([
                        'parent_id' => $parentPageId,
                        'order' => $pageOrder[$key],
                    ]);
                }
            }
        });

        $this->forgetCache();

        return $this->adminTree();
    }

    /**
     * Padre EFECTIVO de cada item, id => parentItemId|null: para una página
     * es el item de `pages.parent_id` (si esa página madre tiene item); para
     * una ruta es su propio `menu_items.parent_id`. SIEMPRE derivado de las
     * páginas, nunca al revés.
     */
    protected function effectiveParents(Collection $items): Collection
    {
        $itemByPageId = $items->where('type', 'page')->whereNotNull('page_id')->keyBy('page_id');

        return $items->mapWithKeys(function (MenuItem $item) use ($itemByPageId) {
            if ($item->type === 'page') {
                $parentPageId = $item->page?->parent_id;
                $parentItem = $parentPageId ? $itemByPageId->get($parentPageId) : null;

                return [$item->id => $parentItem?->id];
            }

            return [$item->id => $item->parent_id];
        });
    }

    /**
     * Árbol recursivo genérico: agrupa por padre EFECTIVO, ordena por
     * `order` y aplica $build a cada item (que decide su forma/si sale). Si
     * $keep, filtra los null que devuelva $build (nodos que no deben
     * pintarse en público); si no, $build siempre devuelve array (admin).
     */
    protected function buildTree(Collection $items, Collection $parents, ?int $parentId, callable $build, bool $keep = false): array
    {
        $children = $items
            ->filter(fn (MenuItem $item) => $parents->get($item->id) === $parentId)
            ->sortBy('order')
            ->map(function (MenuItem $item) use ($items, $parents, $build, $keep) {
                $node = $build($item);
                if ($keep && $node === null) {
                    return null;
                }
                $node['children'] = $this->buildTree($items, $parents, $item->id, $build, $keep);

                return $node;
            });

        if ($keep) {
            $children = $children->filter();
        }

        return $children->values()->all();
    }

    protected function node(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'is_visible' => $item->is_visible,
            'order' => $item->order,
            'route_key' => $item->route_key,
            'page' => $item->page ? [
                'id' => $item->page->id,
                'title' => $item->page->getTranslations('title'),
                'is_published' => $item->page->is_published,
            ] : null,
            'children' => [],
        ];
    }

    /** Nodo público (o null si no debe salir): filtra visibilidad/publicación. */
    protected function publicNode(MenuItem $item): ?array
    {
        if (! $item->is_visible) {
            return null;
        }

        if ($item->type === 'page') {
            if (! $item->page || ! $item->page->is_published) {
                return null;
            }

            return [
                'id' => $item->id,
                'type' => 'page',
                'route_key' => null,
                'page' => [
                    'id' => $item->page->id,
                    'title' => $item->page->getTranslations('title'),
                    'slugs' => $item->page->getTranslations('slug'),
                ],
                'children' => [],
            ];
        }

        // Ruta: sin hijos (un solo nivel; las rutas nunca son madre).
        return [
            'id' => $item->id,
            'type' => 'route',
            'route_key' => $item->route_key,
            'page' => null,
            'children' => [],
        ];
    }

    protected function forgetCache(): void
    {
        Cache::forget('motor.menu.nav');
        Cache::forget('motor.pages.nav');
    }
}
