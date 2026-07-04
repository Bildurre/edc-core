<?php

namespace Bgm\Core\Content\Http\Controllers;

use Bgm\Core\Content\Http\Resources\PageResource;
use Bgm\Core\Content\Models\Page;
use Bgm\Core\Content\PageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * CRUD de páginas del admin (doc 03). Las páginas se manejan por id (el
 * editor es una SPA); el slug traducible es cosa del público.
 */
class PageController extends Controller
{
    public function __construct(protected PageService $service) {}

    /** Catálogo de plantillas de página (config + las del juego). */
    public function templates()
    {
        $templates = collect(config('motor.content.templates', ['default' => 'Default']))
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
            ->values();

        return response()->json(['data' => $templates]);
    }

    /** Todas las páginas (pocas por naturaleza), ordenadas para pintar árbol. */
    public function index(Request $request)
    {
        $pages = Page::query()
            ->filter($request->only('search', 'status'))
            ->withCount('blocks', 'children')
            ->orderByRaw('parent_id is not null, parent_id, `order`, id')
            ->get();

        return PageResource::collection($pages);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $page = $this->service->create($data);

        return (new PageResource($page))->response()->setStatusCode(201);
    }

    public function show(Page $page)
    {
        $page->load('blocks')->loadCount('children');

        return new PageResource($page);
    }

    public function update(Request $request, Page $page)
    {
        $data = $this->validateData($request);
        $this->service->update($page, $data);

        return new PageResource($page->refresh()->load('blocks'));
    }

    public function destroy(Page $page)
    {
        $this->service->delete($page);

        return response()->noContent();
    }

    public function restore(int $id)
    {
        return new PageResource($this->service->restore($id));
    }

    public function forceDestroy(int $id)
    {
        $this->service->forceDelete($id);

        return response()->noContent();
    }

    /** Marca la página como home (solo hay una). */
    public function setHome(Page $page)
    {
        $this->service->setHome($page);

        return new PageResource($page->refresh());
    }

    /** El orden de los ids ES el orden de las páginas (hermanas). */
    public function reorder(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $this->service->reorder($data['ids']);

        return response()->json(['ok' => true]);
    }

    protected function validateData(Request $request): array
    {
        $default = config('motor.default_locale', 'es');
        $templates = array_keys(config('motor.content.templates', ['default' => 'Default']));

        $rules = [
            // 'sometimes': las acciones rápidas del panel mandan solo flags.
            'title' => ['sometimes', 'required', 'array'],
            "title.{$default}" => ['required_with:title', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'meta_title' => ['nullable', 'array'],
            'meta_description' => ['nullable', 'array'],
            'parent_id' => ['nullable', 'integer', 'exists:pages,id'],
            'order' => ['nullable', 'integer'],
            'template' => ['nullable', 'string', 'in:'.implode(',', $templates)],
            'is_published' => ['boolean'],
            'is_printable' => ['boolean'],
        ];

        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["title.{$locale}"] ??= ['nullable', 'string', 'max:255'];
            $rules["description.{$locale}"] = ['nullable', 'string'];
            $rules["meta_title.{$locale}"] = ['nullable', 'string', 'max:255'];
            $rules["meta_description.{$locale}"] = ['nullable', 'string', 'max:500'];
        }

        return $request->validate($rules);
    }
}
