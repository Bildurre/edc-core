<?php

namespace Edc\Core\Menu;

use Edc\Core\Content\Models\Page;
use Edc\Core\Menu\Models\MenuItem;

/**
 * Mantiene los items de tipo 'page'/'route' sincronizados con las páginas del
 * CRM y con `motor.menu.routes` (doc 10 ampliado): garantiza exactamente un
 * item por cada página NO home (publicada o no, cualquiera que sea su
 * `parent_id` — la jerarquía del menú se deriva de ahí, ver MenuService) y
 * por cada route_key declarada, añade los nuevos al final de la raíz
 * (visibles) y borra los huérfanos (página borrada o convertida en home;
 * clave que ha desaparecido de la config).
 */
class MenuSync
{
    public function sync(): void
    {
        $this->syncPages();
        $this->syncRoutes();
    }

    protected function syncPages(): void
    {
        $pageIds = Page::query()->where('is_home', false)->pluck('id')->all();

        $existing = MenuItem::query()->where('type', 'page')->pluck('page_id')->all();
        $missing = array_diff($pageIds, $existing);

        $order = $this->nextRootOrder();
        foreach ($missing as $pageId) {
            MenuItem::create([
                'type' => 'page',
                'page_id' => $pageId,
                'order' => $order++,
                'is_visible' => true,
            ]);
        }

        // Huérfanas: la página ya no existe (borrada) o ha pasado a ser home.
        $query = MenuItem::query()->where('type', 'page');
        if ($pageIds) {
            $query->whereNotIn('page_id', $pageIds);
        }
        $query->delete();
    }

    protected function syncRoutes(): void
    {
        $keys = array_values(config('motor.menu.routes', []));

        $existing = MenuItem::query()->where('type', 'route')->pluck('route_key')->all();
        $missing = array_diff($keys, $existing);

        $order = $this->nextRootOrder();
        foreach ($missing as $key) {
            MenuItem::create([
                'type' => 'route',
                'route_key' => $key,
                'order' => $order++,
                'is_visible' => true,
            ]);
        }

        // Huérfanas: la clave ya no está en la config del juego.
        $query = MenuItem::query()->where('type', 'route');
        if ($keys) {
            $query->whereNotIn('route_key', $keys);
        }
        $query->delete();
    }

    protected function nextRootOrder(): int
    {
        $max = MenuItem::query()->root()->max('order');

        return $max === null ? 0 : $max + 1;
    }
}
