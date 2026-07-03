<?php

namespace Bgm\Core\Previews\Http\Controllers;

use Bgm\Core\Previews\PreviewRegistry;
use Bgm\Core\Previews\PreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Gestor de previews del admin: estado global, listado por entidad con el
 * detalle por locale, lotes por tipo (generar pendientes / regenerar todo /
 * borrar todo), acciones individuales y limpieza de huérfanos.
 * La UI vive en @bgm/admin-kit (PreviewManager).
 */
class PreviewController extends Controller
{
    public function __construct(
        protected PreviewRegistry $registry,
        protected PreviewService $service,
    ) {}

    /** Estado por tipo registrado: total, completas, pendientes. */
    public function index(): JsonResponse
    {
        $data = [];
        foreach ($this->service->status() as $key => $info) {
            $data[] = [
                'key' => $key,
                'model' => class_basename($info['model']),
                'total' => $info['total'],
                'complete' => $info['complete'],
                'pending' => $info['pending'],
                'locales' => $info['locales'],
            ];
        }

        return response()->json(['data' => $data]);
    }

    /** Entidades de un tipo, paginadas, con su estado de preview por locale. */
    public function items(Request $request, string $entity): JsonResponse
    {
        abort_unless($this->registry->has($entity), 404);

        $locales = array_keys(config('motor.locales', []));
        $locale = app()->getLocale();

        $model = $this->registry->modelFor($entity);
        $query = $model::query();

        // Buscador del selector del panel: usa las columnas $searchable de la
        // entidad (HasFilters) si el modelo lo trae.
        $q = trim((string) $request->query('q', ''));
        if ($q !== '' && method_exists($model, 'scopeFilter')) {
            $query->filter(['search' => $q]);
        }

        $page = $query->orderByDesc('id')->paginate(24);

        return response()->json([
            'data' => collect($page->items())->map(fn ($item) => [
                'id' => $item->getKey(),
                'label' => $item->previewLabel($locale),
                'previews' => collect($locales)
                    ->mapWithKeys(fn ($l) => [$l => $item->previewUrl($l, $entity)])
                    ->all(),
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** Encola los renders que FALTAN de un tipo (opcional locale en el cuerpo). */
    public function generateType(Request $request, string $entity): JsonResponse
    {
        abort_unless($this->registry->has($entity), 404);

        $queued = $this->service->queueType($entity, onlyMissing: true, locales: $this->locales($request));

        return response()->json([
            'message' => trans_choice('motor::motor.renders_queued', $queued, ['count' => $queued]),
            'queued' => $queued,
        ], 202);
    }

    /** Encola la regeneración de TODO un tipo (opcional locale en el cuerpo). */
    public function regenerateType(Request $request, string $entity): JsonResponse
    {
        abort_unless($this->registry->has($entity), 404);

        $queued = $this->service->queueType($entity, onlyMissing: false, locales: $this->locales($request));

        return response()->json([
            'message' => trans_choice('motor::motor.renders_queued', $queued, ['count' => $queued]),
            'queued' => $queued,
        ], 202);
    }

    /** Borra las previews de TODAS las entidades de un tipo. */
    public function destroyType(string $entity): JsonResponse
    {
        abort_unless($this->registry->has($entity), 404);

        $count = $this->service->deleteType($entity);

        return response()->json(['message' => __('motor::motor.previews_deleted'), 'entities' => $count]);
    }

    /** Elimina ficheros huérfanos (body: dry_run para solo listar). */
    public function clean(Request $request): JsonResponse
    {
        $dryRun = $request->boolean('dry_run');
        $orphans = $this->service->cleanOrphans($dryRun);

        return response()->json([
            'message' => $dryRun
                ? trans_choice('motor::motor.orphans_found', count($orphans), ['count' => count($orphans)])
                : trans_choice('motor::motor.orphans_deleted', count($orphans), ['count' => count($orphans)]),
            'orphans' => $orphans,
            'dry_run' => $dryRun,
        ]);
    }

    /** URLs de los PNG de una entidad concreta. */
    public function show(string $entity, int $id): JsonResponse
    {
        $model = $this->find($entity, $id);

        return response()->json(['data' => $model->previewUrls($entity)]);
    }

    /** Encola la regeneración de una entidad (opcional locale en el cuerpo). */
    public function regenerate(Request $request, string $entity, int $id): JsonResponse
    {
        $model = $this->find($entity, $id);

        $model->regeneratePreviews($this->locales($request), types: [$entity]);

        return response()->json(['message' => __('motor::motor.previews_queued')], 202);
    }

    /** Borra los PNG de una entidad concreta (solo esta preview). */
    public function destroy(string $entity, int $id): JsonResponse
    {
        $this->find($entity, $id)->deletePreviews($entity);

        return response()->json(['message' => __('motor::motor.previews_deleted')]);
    }

    protected function find(string $entity, int $id)
    {
        abort_unless($this->registry->has($entity), 404);

        return $this->registry->modelFor($entity)::query()->findOrFail($id);
    }

    /**
     * Un `locale` EN EL CUERPO limita la acción a ese locale; si no, todos.
     * Ojo: solo el cuerpo — el ?locale de la query es el locale de contenido
     * que el admin añade a TODAS las peticiones (no un limitador).
     */
    protected function locales(Request $request): ?array
    {
        $locale = $request->json('locale', $request->post('locale'));

        return is_string($locale) && $locale !== '' ? [$locale] : null;
    }
}
