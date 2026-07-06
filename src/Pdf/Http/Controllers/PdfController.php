<?php

namespace Edc\Core\Pdf\Http\Controllers;

use Edc\Core\Pdf\Http\Resources\GeneratedPdfResource;
use Edc\Core\Pdf\Models\GeneratedPdf;
use Edc\Core\Pdf\PdfExportRegistry;
use Edc\Core\Pdf\PdfService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestión de PDF desde el admin (doc 02): listar por export/entidad, generar,
 * regenerar y borrar con un clic. La UI vive en @edc-motor/admin-kit (PdfManager).
 */
class PdfController extends Controller
{
    public function __construct(
        protected PdfExportRegistry $exports,
        protected PdfService $service,
    ) {}

    /**
     * Catálogo de exports registrados por el juego, para el gestor del admin:
     * tipo, si es global o por entidad, layout, (si aplica) las entidades
     * dueñas disponibles y las estadísticas por idioma (total de piezas
     * esperadas y cuántas están listas — mismo resumen que las previews).
     */
    public function exports(): JsonResponse
    {
        $locale = app()->getLocale();
        $locales = array_keys(config('motor.locales', []));

        $data = collect($this->exports->types())->map(function (string $type) use ($locale, $locales) {
            $export = $this->exports->get($type);
            $sources = $export->sourceModel() !== null ? $export->sources($locale) : [];
            $total = $export->sourceModel() !== null ? count($sources) : 1;

            // Listos por idioma (los permanentes del export).
            $ready = GeneratedPdf::query()
                ->where('type', $type)
                ->where('status', GeneratedPdf::STATUS_READY)
                ->selectRaw('locale, count(*) as n')
                ->groupBy('locale')
                ->pluck('n', 'locale');

            return [
                'type' => $type,
                'global' => $export->sourceModel() === null,
                'layout' => $export->layout(),
                'sources' => $sources,
                'stats' => [
                    'total' => $total,
                    'locales' => collect($locales)->mapWithKeys(
                        fn ($code) => [$code => min((int) ($ready[$code] ?? 0), $total)]
                    ),
                ],
            ];
        });

        return response()->json(['data' => $data]);
    }

    /** PDF de un export (y entidad dueña, si el export la tiene). */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'source_id' => ['nullable', 'integer'],
        ]);

        abort_unless($this->exports->has($data['type']), 404);

        $pdfs = GeneratedPdf::query()
            ->where('type', $data['type'])
            ->when(
                isset($data['source_id']),
                fn ($q) => $q->where('source_id', $data['source_id']),
                fn ($q) => $q->whereNull('source_id'),
            )
            ->orderBy('locale')
            ->get();

        return GeneratedPdfResource::collection($pdfs)->response();
    }

    /** Genera (o regenera) el PDF del export para uno o todos los locales. */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'source_id' => ['nullable', 'integer'],
            'layout' => ['nullable', 'string'],
        ]);

        abort_unless($this->exports->has($data['type']), 404);

        $export = $this->exports->get($data['type']);
        $source = null;

        if ($export->sourceModel() !== null) {
            abort_unless(isset($data['source_id']), 422, 'source_id requerido');
            $source = $export->sourceModel()::query()->findOrFail($data['source_id']);
        }

        // Por defecto se generan TODOS los locales. Un `locale` en el CUERPO
        // limita a uno; el ?locale de la query no cuenta (es el locale de
        // contenido que el admin añade a todas sus peticiones).
        $available = array_keys(config('motor.locales', []));
        $locale = $request->json('locale', $request->post('locale'));
        abort_unless($locale === null || in_array($locale, $available, true), 422, 'locale desconocido');

        $locales = $locale !== null ? [$locale] : $available;

        $pdfs = collect($locales)->map(
            fn ($locale) => $this->service->generate($data['type'], $source, $locale, $data['layout'] ?? null)
        );

        return GeneratedPdfResource::collection($pdfs)
            ->additional(['message' => __('motor::motor.pdfs_queued')])
            ->response()
            ->setStatusCode(202);
    }

    /**
     * Genera SOLO lo que falta del export: combos (entidad, idioma) sin PDF
     * o cuyo último intento falló. Espejo de "generar faltantes" de previews.
     */
    public function generateMissing(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'string']]);
        abort_unless($this->exports->has($data['type']), 404);

        $existing = GeneratedPdf::query()
            ->where('type', $data['type'])
            ->where('status', '!=', GeneratedPdf::STATUS_FAILED)
            ->get()
            ->keyBy(fn (GeneratedPdf $pdf) => ($pdf->source_id ?? 'global').':'.$pdf->locale);

        $queued = 0;
        foreach ($this->combos($data['type']) as [$source, $locale]) {
            if ($existing->has(($source?->getKey() ?? 'global').':'.$locale)) {
                continue;
            }
            $this->service->generate($data['type'], $source, $locale);
            $queued++;
        }

        return response()->json(['message' => __('motor::motor.pdfs_queued'), 'queued' => $queued], 202);
    }

    /** Regenera TODOS los PDF del export (todas las entidades y todos los idiomas). */
    public function regenerateAll(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'string']]);
        abort_unless($this->exports->has($data['type']), 404);

        $queued = 0;
        foreach ($this->combos($data['type']) as [$source, $locale]) {
            $this->service->generate($data['type'], $source, $locale);
            $queued++;
        }

        return response()->json(['message' => __('motor::motor.pdfs_queued'), 'queued' => $queued], 202);
    }

    /** Borra TODOS los PDF del export (fichero incluido). */
    public function destroyType(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'string']]);
        abort_unless($this->exports->has($data['type']), 404);

        GeneratedPdf::query()
            ->where('type', $data['type'])
            ->get()
            ->each(fn (GeneratedPdf $pdf) => $this->service->delete($pdf));

        return response()->json(['message' => __('motor::motor.pdf_deleted')]);
    }

    /**
     * Combos (entidad dueña o null, idioma) que un export debería tener:
     * el producto de sus fuentes por los locales configurados.
     *
     * @return array<int, array{0: Model|null, 1: string}>
     */
    protected function combos(string $type): array
    {
        $export = $this->exports->get($type);
        $locales = array_keys(config('motor.locales', []));

        $sources = collect([null]);
        if ($export->sourceModel() !== null) {
            $ids = collect($export->sources(app()->getLocale()))->pluck('id');
            $sources = $export->sourceModel()::query()->findMany($ids)->values();
        }

        $combos = [];
        foreach ($sources as $source) {
            foreach ($locales as $locale) {
                $combos[] = [$source, $locale];
            }
        }

        return $combos;
    }

    public function regenerate(GeneratedPdf $pdf): JsonResponse
    {
        $this->service->regenerate($pdf);

        return (new GeneratedPdfResource($pdf))
            ->additional(['message' => __('motor::motor.pdfs_queued')])
            ->response()
            ->setStatusCode(202);
    }

    public function destroy(GeneratedPdf $pdf): JsonResponse
    {
        $this->service->delete($pdf);

        return response()->json(['message' => __('motor::motor.pdf_deleted')]);
    }

    /**
     * Apartado público de Descargas (doc 10, como en CDL): los PDF
     * PERMANENTES listos, agrupados por tipo de export. Sin auth: es el
     * expositor. La SPA pone las etiquetas de cada tipo.
     */
    public function downloads(): JsonResponse
    {
        $disk = Storage::disk(config('motor.pdf.disk'));

        $groups = GeneratedPdf::query()
            ->where('is_permanent', true)
            ->where('status', GeneratedPdf::STATUS_READY)
            ->orderBy('filename')
            ->orderBy('locale')
            ->get()
            ->groupBy('type')
            ->map(fn ($pdfs, $type) => [
                'type' => $type,
                'items' => $pdfs->map(fn (GeneratedPdf $pdf) => [
                    'id' => $pdf->id,
                    'filename' => $pdf->filename,
                    'locale' => $pdf->locale,
                    'url' => url("/api/pdfs/{$pdf->id}/download"),
                    'size' => $pdf->path && $disk->exists($pdf->path) ? $disk->size($pdf->path) : null,
                    'generated_at' => $pdf->generated_at?->toIso8601String(),
                ])->values(),
            ])
            ->values();

        return response()->json(['data' => $groups]);
    }

    /**
     * Descarga. Los permanentes son públicos (expositor); los temporales solo
     * para su dueño — usuario logueado o token de invitado — o un admin.
     */
    public function download(Request $request, GeneratedPdf $pdf): StreamedResponse
    {
        abort_unless($pdf->isReady() && ! $pdf->isExpired(), 404);

        if (! $pdf->is_permanent) {
            // Guard por defecto (sesión/tests) con fallback al token Sanctum:
            // la ruta es pública y el guard se resuelve bajo demanda.
            $user = $request->user() ?? $request->user('sanctum');
            $guestToken = (string) $request->header('X-Collection-Token', $request->query('token', ''));
            abort_unless(
                ($user && ($user->getKey() === $pdf->owner_id || $user->canAccessAdmin()))
                || ($pdf->guest_token !== null && $guestToken !== '' && hash_equals($pdf->guest_token, $guestToken)),
                403,
            );
        }

        return Storage::disk(config('motor.pdf.disk'))
            ->download($pdf->path, "{$pdf->filename}.pdf");
    }
}
