<?php

namespace Edc\Core\Menu\Http\Controllers;

use Edc\Core\Menu\MenuService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Gestión del menú configurable de la web pública (doc 10 ampliado). Reparto
 * con las páginas: mismo grupo/permiso (can:manage-web, "es la web"). Sin
 * grupos ni endpoints de escritura por item: el admin trabaja sobre una
 * copia local del árbol y lo manda ENTERO de una vez (patrón "Guardar").
 */
class MenuController extends Controller
{
    public function __construct(protected MenuService $service) {}

    /** Sincroniza (páginas/rutas nuevas u huérfanas) y devuelve el árbol completo. */
    public function index()
    {
        $this->service->sync();

        return response()->json(['data' => $this->service->adminTree()]);
    }

    /**
     * Reemplaza el árbol entero: `items` es la lista completa (madre seguida
     * de sus hijas), cada una con su `parent_id` de destino (null = raíz;
     * el id de una página raíz para anidar bajo ella) y su visibilidad. El
     * orden del array ES el orden del menú.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.parent_id' => ['nullable', 'integer'],
            'items.*.is_visible' => ['required', 'boolean'],
        ]);

        $tree = $this->service->replaceTree($data['items']);

        return response()->json(['data' => $tree]);
    }
}
