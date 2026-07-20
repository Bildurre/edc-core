<?php

namespace Edc\Core\Menu;

use Edc\Core\Menu\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * CRUD del menú configurable (doc 10 ampliado) + construcción del árbol para
 * el admin (todo, con la página embebida) y para el público (solo visibles,
 * páginas además publicadas, grupos sin hijos visibles fuera).
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

        return $this->children($items, null)->all();
    }

    /** Un solo nodo (tras crear/editar), en la misma forma que el árbol. */
    public function nodeFor(MenuItem $item): array
    {
        $item->load('page');

        return $this->node($item, collect());
    }

    /** Árbol público: solo visibles; páginas publicadas; grupos vacíos fuera. */
    public function publicTree(): array
    {
        $items = MenuItem::query()->with('page')->orderBy('order')->get();

        return $items->where('parent_id', null)
            ->map(fn (MenuItem $item) => $this->publicNode($item, $items))
            ->filter()
            ->values()
            ->all();
    }

    public function createGroup(array $data): MenuItem
    {
        $item = new MenuItem([
            'type' => 'group',
            'parent_id' => null,
            'order' => $this->nextRootOrder(),
            'is_visible' => true,
        ]);
        $item->replaceTranslations('label', $this->cleanLabel($data['label'] ?? []));
        $item->save();
        $this->forgetCache();

        return $item;
    }

    public function updateItem(MenuItem $item, array $data): MenuItem
    {
        if (array_key_exists('is_visible', $data)) {
            $item->is_visible = (bool) $data['is_visible'];
        }
        if (array_key_exists('parent_id', $data)) {
            $item->parent_id = $data['parent_id'];
        }
        if (array_key_exists('label', $data) && $item->type === 'group') {
            $item->replaceTranslations('label', $this->cleanLabel($data['label'] ?? []));
        }
        $item->save();
        $this->forgetCache();

        return $item;
    }

    /** La lista de ids marca el orden (0, 1, 2…); cada item conserva su padre. */
    public function reorder(array $ids): void
    {
        foreach (array_values($ids) as $index => $id) {
            MenuItem::query()->whereKey($id)->update(['order' => $index]);
        }
        $this->forgetCache();
    }

    /** Solo grupos: sus hijos pasan a la raíz (no se pierden). */
    public function deleteGroup(MenuItem $item): void
    {
        $item->children()->update(['parent_id' => null]);
        $item->delete();
        $this->forgetCache();
    }

    protected function nextRootOrder(): int
    {
        $max = MenuItem::query()->root()->max('order');

        return $max === null ? 0 : $max + 1;
    }

    protected function cleanLabel(array $label): array
    {
        return array_filter($label, fn ($v) => $v !== null && $v !== '');
    }

    /** Hijos directos de $parentId, ya transformados (recursivo). */
    protected function children(Collection $items, ?int $parentId): Collection
    {
        return $items->where('parent_id', $parentId)
            ->map(fn (MenuItem $item) => $this->node($item, $items))
            ->values();
    }

    protected function node(MenuItem $item, Collection $items): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'is_visible' => $item->is_visible,
            'order' => $item->order,
            'route_key' => $item->route_key,
            'label' => $item->type === 'group' ? $item->getTranslations('label') : null,
            'page' => $item->page ? [
                'id' => $item->page->id,
                'title' => $item->page->getTranslations('title'),
                'is_published' => $item->page->is_published,
            ] : null,
            'children' => $items->isEmpty() ? [] : $this->children($items, $item->id)->all(),
        ];
    }

    /** Nodo público (o null si no debe salir): filtra visibilidad/publicación. */
    protected function publicNode(MenuItem $item, Collection $items): ?array
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
                'label' => null,
                'route_key' => null,
                'page' => [
                    'id' => $item->page->id,
                    'title' => $item->page->getTranslations('title'),
                    'slugs' => $item->page->getTranslations('slug'),
                ],
                'children' => [],
            ];
        }

        if ($item->type === 'route') {
            return [
                'id' => $item->id,
                'type' => 'route',
                'label' => null,
                'route_key' => $item->route_key,
                'page' => null,
                'children' => [],
            ];
        }

        // Grupo: solo sale si le queda al menos un hijo visible.
        $children = $items->where('parent_id', $item->id)
            ->map(fn (MenuItem $child) => $this->publicNode($child, $items))
            ->filter()
            ->values()
            ->all();

        if (! $children) {
            return null;
        }

        return [
            'id' => $item->id,
            'type' => 'group',
            'label' => $item->getTranslations('label'),
            'route_key' => null,
            'page' => null,
            'children' => $children,
        ];
    }

    protected function forgetCache(): void
    {
        Cache::forget('motor.menu.nav');
    }
}
