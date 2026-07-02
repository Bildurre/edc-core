<?php

namespace Bgm\Core\Pdf\Http\Controllers;

use Bgm\Core\Pdf\Http\Resources\GeneratedPdfResource;
use Bgm\Core\Pdf\Models\GeneratedPdf;
use Bgm\Core\Pdf\PdfExportRegistry;
use Bgm\Core\Pdf\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestión de PDF desde el admin (doc 02): listar por export/entidad, generar,
 * regenerar y borrar con un clic. La UI vive en @bgm/admin-kit (PdfManager).
 */
class PdfController extends Controller
{
    public function __construct(
        protected PdfExportRegistry $exports,
        protected PdfService $service,
    ) {}

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
            'locale' => ['nullable', 'string'],
            'layout' => ['nullable', 'string'],
        ]);

        abort_unless($this->exports->has($data['type']), 404);

        $export = $this->exports->get($data['type']);
        $source = null;

        if ($export->sourceModel() !== null) {
            abort_unless(isset($data['source_id']), 422, 'source_id requerido');
            $source = $export->sourceModel()::query()->findOrFail($data['source_id']);
        }

        $locales = isset($data['locale'])
            ? [$data['locale']]
            : array_keys(config('motor.locales', []));

        $pdfs = collect($locales)->map(
            fn ($locale) => $this->service->generate($data['type'], $source, $locale, $data['layout'] ?? null)
        );

        return GeneratedPdfResource::collection($pdfs)
            ->additional(['message' => __('motor::motor.pdfs_queued')])
            ->response()
            ->setStatusCode(202);
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
     * Descarga. Los permanentes son públicos (expositor); los temporales solo
     * para su dueño (o un admin).
     */
    public function download(Request $request, GeneratedPdf $pdf): StreamedResponse
    {
        abort_unless($pdf->isReady() && ! $pdf->isExpired(), 404);

        if (! $pdf->is_permanent) {
            $user = $request->user();
            abort_unless(
                $user && ($user->getKey() === $pdf->owner_id || $user->canAccessAdmin()),
                403,
            );
        }

        return Storage::disk(config('motor.pdf.disk'))
            ->download($pdf->path, "{$pdf->filename}.pdf");
    }
}
