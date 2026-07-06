<?php

namespace Edc\Core\Content\Http\Controllers;

use Edc\Core\Content\BlockService;
use Edc\Core\Content\BlockTypeRegistry;
use Edc\Core\Content\Http\Resources\BlockResource;
use Edc\Core\Content\Models\Block;
use Edc\Core\Content\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * CRUD de bloques del admin (doc 03). La validación de `settings` se deriva
 * del esquema de campos del BlockType: añadir un tipo no toca este código.
 */
class BlockController extends Controller
{
    public function __construct(
        protected BlockService $service,
        protected BlockTypeRegistry $registry,
    ) {}

    public function index(Page $page)
    {
        return BlockResource::collection($page->blocks()->get());
    }

    public function store(Request $request, Page $page)
    {
        $type = $request->validate([
            'type' => ['required', 'string', Rule::in($this->registry->keys())],
        ])['type'];

        $data = $request->validate([
            'is_printable' => ['boolean'],
            'is_indexable' => ['boolean'],
            ...$this->registry->get($type)->rules(),
        ]);

        $block = $this->service->create($page, $type, $data);

        return (new BlockResource($block))->response()->setStatusCode(201);
    }

    public function update(Request $request, Block $block)
    {
        // Sin `settings` en la petición solo se tocan los flags (acciones
        // rápidas del panel): no se exige el esquema completo.
        $rules = ['is_printable' => ['boolean'], 'is_indexable' => ['boolean']];
        if ($request->has('settings')) {
            $rules += $this->registry->get($block->type)->rules();
        }

        $data = $request->validate($rules);

        $this->service->update($block, $data);

        return new BlockResource($block->refresh());
    }

    public function destroy(Block $block)
    {
        $this->service->delete($block);

        return response()->noContent();
    }

    /** El orden de los ids ES el orden de los bloques de la página. */
    public function reorder(Request $request, Page $page)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $this->service->reorder($page, $data['ids']);

        return BlockResource::collection($page->blocks()->get());
    }
}
