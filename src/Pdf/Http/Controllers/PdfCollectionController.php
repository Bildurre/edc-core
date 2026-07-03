<?php

namespace Bgm\Core\Pdf\Http\Controllers;

use Bgm\Core\Pdf\Http\Resources\GeneratedPdfResource;
use Bgm\Core\Pdf\Models\GeneratedPdf;
use Bgm\Core\Pdf\Models\PdfCollectionItem;
use Bgm\Core\Pdf\PdfService;
use Bgm\Core\Previews\PreviewRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Colección temporal "para imprimir" del usuario autenticado (doc 02):
 * añadir/quitar entidades renderizables con copias y generar su PDF temporal.
 */
class PdfCollectionController extends Controller
{
    public function __construct(
        protected PreviewRegistry $previewables,
        protected PdfService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $locale = app()->getLocale();

        $items = PdfCollectionItem::query()
            ->where('user_id', $request->user()->getKey())
            ->orderBy('id')
            ->get()
            ->map(function (PdfCollectionItem $item) use ($locale) {
                $model = $this->previewables->modelFor($item->entity)::query()->find($item->entity_id);

                return [
                    'id' => $item->id,
                    'entity' => $item->entity,
                    'entity_id' => $item->entity_id,
                    'copies' => $item->copies,
                    'label' => $model?->previewLabel($locale),
                    'preview' => $model?->previewUrl($locale, $item->entity),
                    'missing' => $model === null,
                ];
            });

        return response()->json(['data' => $items]);
    }

    /** Añade una entidad (o actualiza sus copias si ya estaba). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity' => ['required', 'string', Rule::in($this->previewables->keys())],
            'id' => ['required', 'integer'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        // La entidad tiene que existir (y ser visible).
        $this->previewables->modelFor($data['entity'])::query()->findOrFail($data['id']);

        PdfCollectionItem::query()->updateOrCreate(
            [
                'user_id' => $request->user()->getKey(),
                'entity' => $data['entity'],
                'entity_id' => $data['id'],
            ],
            ['copies' => $data['copies'] ?? 1],
        );

        return $this->index($request)->setStatusCode(201);
    }

    public function destroy(Request $request, int $item): JsonResponse
    {
        PdfCollectionItem::query()
            ->where('user_id', $request->user()->getKey())
            ->whereKey($item)
            ->delete();

        return $this->index($request);
    }

    public function clear(Request $request): JsonResponse
    {
        PdfCollectionItem::query()->where('user_id', $request->user()->getKey())->delete();

        return response()->json(['data' => []]);
    }

    /** Genera el PDF temporal de la colección actual. */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'locale' => ['nullable', 'string'],
            'layout' => ['nullable', 'string'],
        ]);

        $items = PdfCollectionItem::query()
            ->where('user_id', $request->user()->getKey())
            ->get()
            ->map(fn (PdfCollectionItem $item) => [
                'entity' => $item->entity,
                'id' => $item->entity_id,
                'copies' => $item->copies,
            ])
            ->all();

        abort_if($items === [], 422, __('motor::motor.collection_empty'));

        $pdf = $this->service->generateCollection(
            $request->user(),
            $items,
            $data['locale'] ?? app()->getLocale(),
            $data['layout'] ?? null,
        );

        return (new GeneratedPdfResource($pdf))
            ->additional(['message' => __('motor::motor.pdfs_queued')])
            ->response()
            ->setStatusCode(202);
    }

    /** Estado de un PDF temporal propio (para sondear hasta 'ready'). */
    public function show(Request $request, int $pdf): JsonResponse
    {
        $model = GeneratedPdf::query()
            ->where('owner_id', $request->user()->getKey())
            ->findOrFail($pdf);

        return (new GeneratedPdfResource($model))->response();
    }
}
