<?php

namespace Edc\Core\Menu\Http\Controllers;

use Edc\Core\Menu\MenuService;
use Edc\Core\Menu\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * Gestión del menú configurable de la web pública (doc 10 ampliado). Reparto
 * con las páginas: mismo grupo/permiso (can:manage-web, "es la web").
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

    /** Crea un grupo (carpeta) al final de la raíz. */
    public function storeGroup(Request $request)
    {
        $data = $request->validate($this->labelRules());
        $item = $this->service->createGroup($data);

        return response()->json(['data' => $this->service->nodeFor($item)], 201);
    }

    public function update(Request $request, MenuItem $item)
    {
        $rules = [
            'is_visible' => ['sometimes', 'boolean'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:menu_items,id'],
        ];
        if ($item->type === 'group') {
            $rules += $this->labelRules(required: false);
        }
        $data = $request->validate($rules);

        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $parent = MenuItem::find($data['parent_id']);
            if (! $parent || $parent->type !== 'group') {
                throw ValidationException::withMessages([
                    'parent_id' => 'El padre debe ser un grupo.',
                ]);
            }
            if ($item->type === 'group') {
                throw ValidationException::withMessages([
                    'parent_id' => 'Un grupo no puede colgar de otro grupo.',
                ]);
            }
        }

        $this->service->updateItem($item, $data);

        return response()->json(['data' => $this->service->nodeFor($item->refresh())]);
    }

    /** El orden de los ids ES el orden de esos items (mismo padre cada uno). */
    public function reorder(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $this->service->reorder($data['ids']);

        return response()->json(['ok' => true]);
    }

    /** Solo grupos: sus hijos pasan a la raíz. */
    public function destroy(MenuItem $item)
    {
        if ($item->type !== 'group') {
            throw ValidationException::withMessages([
                'type' => 'Solo se pueden borrar grupos.',
            ]);
        }
        $this->service->deleteGroup($item);

        return response()->noContent();
    }

    protected function labelRules(bool $required = true): array
    {
        $default = config('motor.default_locale', 'es');
        $rules = [
            'label' => [$required ? 'required' : 'sometimes', 'array'],
            "label.{$default}" => [$required ? 'required' : 'sometimes', 'string', 'max:255'],
        ];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["label.{$locale}"] ??= ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }
}
