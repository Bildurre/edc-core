<?php

namespace Bgm\Core\Pdf;

use Bgm\Core\Pdf\Jobs\GeneratePdfJob;
use Bgm\Core\Pdf\Models\GeneratedPdf;
use Bgm\Core\Previews\PreviewRegistry;
use Bgm\Core\Previews\PreviewService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Punto único de generación de PDF (doc 02). Regenerar = volver a llamar:
 * el servicio reutiliza/borra lo anterior y rehace. Nada de export services
 * copy-paste por tipo: el contenido lo describe el PdfExport del juego.
 */
class PdfService
{
    /** Type reservado para las colecciones temporales a la carta. */
    public const COLLECTION_TYPE = 'collection';

    public function __construct(
        protected PdfExportRegistry $exports,
        protected PreviewRegistry $previewables,
        protected PreviewService $previews,
        protected PdfComposer $composer,
    ) {}

    /**
     * Genera (o regenera) el PDF permanente de un export para un locale.
     * Si ya existía para (type, source, locale) se reutiliza el registro.
     */
    public function generate(
        string $type,
        ?Model $source,
        string $locale,
        ?string $layout = null,
        bool $sync = false,
    ): GeneratedPdf {
        $export = $this->exports->get($type);
        $this->assertSourceMatches($export, $source);

        $pdf = GeneratedPdf::query()
            ->where('type', $type)
            ->where('source_type', $source?->getMorphClass())
            ->where('source_id', $source?->getKey())
            ->where('locale', $locale)
            ->where('is_permanent', true)
            ->first();

        $pdf ??= new GeneratedPdf;
        $pdf->fill([
            'type' => $type,
            'locale' => $locale,
            'layout' => $layout ?? $export->layout(),
            'filename' => $export->filename($source, $locale),
            'status' => GeneratedPdf::STATUS_PENDING,
            'error' => null,
            'is_permanent' => true,
        ]);
        $pdf->source()->associate($source);
        $pdf->save();

        $this->dispatch($pdf, $sync);

        return $pdf;
    }

    /**
     * Genera el PDF temporal de una colección a la carta (doc 02).
     *
     * @param  array<int, array{entity: string, id: int|string, copies: int}>  $items
     */
    public function generateCollection(
        User $owner,
        array $items,
        string $locale,
        ?string $layout = null,
        bool $sync = false,
    ): GeneratedPdf {
        if ($items === []) {
            throw new InvalidArgumentException('La colección está vacía.');
        }

        $pdf = GeneratedPdf::create([
            'type' => self::COLLECTION_TYPE,
            'owner_id' => $owner->getKey(),
            'locale' => $locale,
            'layout' => $layout ?? config('motor.pdf.default_layout', 'card'),
            'filename' => self::COLLECTION_TYPE."-{$owner->getKey()}-{$locale}",
            'status' => GeneratedPdf::STATUS_PENDING,
            'payload' => array_values($items),
            'is_permanent' => false,
            'expires_at' => now()->addHours((int) config('motor.pdf.temporary_ttl', 24)),
        ]);

        $this->dispatch($pdf, $sync);

        return $pdf;
    }

    /** Reencola la generación de un PDF existente. */
    public function regenerate(GeneratedPdf $pdf, bool $sync = false): GeneratedPdf
    {
        $pdf->update(['status' => GeneratedPdf::STATUS_PENDING, 'error' => null]);
        $this->dispatch($pdf, $sync);

        return $pdf;
    }

    public function delete(GeneratedPdf $pdf): void
    {
        $pdf->deleteFile();
        $pdf->delete();
    }

    /** Borra los temporales caducados (fichero + registro). */
    public function cleanupExpired(): int
    {
        $count = 0;

        GeneratedPdf::query()
            ->where('is_permanent', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->cursor()
            ->each(function (GeneratedPdf $pdf) use (&$count) {
                $this->delete($pdf);
                $count++;
            });

        return $count;
    }

    /**
     * Compone el PDF (lo llama el job): resuelve ítems y previews que falten,
     * ensambla con DomPDF, guarda versionado y borra el fichero anterior.
     */
    public function compose(GeneratedPdf $pdf): void
    {
        $items = $this->itemsFor($pdf);

        $slots = $this->composer->expand($items, fn (PrintableItem $item) => $this->resolveImage($item, $pdf->locale));

        if ($slots === []) {
            throw new PdfCompositionException(__('motor::motor.pdf_no_items'));
        }

        $layout = PrintLayout::fromConfig($pdf->layout);
        $view = $pdf->type === self::COLLECTION_TYPE ? null : $this->exports->get($pdf->type)->view();

        $previous = $pdf->path;
        $path = $this->composer->compose($pdf, $slots, $layout, $view);

        $pdf->update([
            'path' => $path,
            'status' => GeneratedPdf::STATUS_READY,
            'error' => null,
            'generated_at' => now(),
        ]);

        if ($previous && $previous !== $path) {
            Storage::disk(config('motor.pdf.disk'))->delete($previous);
        }
    }

    protected function dispatch(GeneratedPdf $pdf, bool $sync): void
    {
        $sync
            ? GeneratePdfJob::dispatchSync($pdf->id)
            : GeneratePdfJob::dispatch($pdf->id);
    }

    /** @return PrintableItem[] */
    protected function itemsFor(GeneratedPdf $pdf): array
    {
        if ($pdf->type === self::COLLECTION_TYPE) {
            $items = [];
            foreach ($pdf->payload ?? [] as $row) {
                $model = $this->previewables->modelFor($row['entity'])::query()->find($row['id']);
                if ($model !== null) {
                    // La clave elegida por el usuario ES la preview a imprimir.
                    $items[] = PrintableItem::preview($model, (int) ($row['copies'] ?? 1), preview: $row['entity']);
                }
            }

            return $items;
        }

        $export = $this->exports->get($pdf->type);

        return $export->items($pdf->source, $pdf->locale);
    }

    /** Imagen imprimible del ítem: la dada, o la preview (generándola si falta). */
    protected function resolveImage(PrintableItem $item, string $locale): ?string
    {
        if ($item->image !== null) {
            return $item->image;
        }

        $entity = $item->previewable;

        if (! $entity->hasPreview($locale, $item->previewType)) {
            // El PDF necesita el PNG sí o sí: se genera en el momento.
            $this->previews->generate($entity, $locale, $item->previewType);
            $entity->refresh();
        }

        $disk = config('motor.previews.disk');
        $path = $entity->previewPath($locale, $item->previewType);

        // DomPDF prefiere rutas locales; para discos remotos, la URL.
        return config("filesystems.disks.{$disk}.driver") === 'local'
            ? Storage::disk($disk)->path($path)
            : Storage::disk($disk)->url($path);
    }

    protected function assertSourceMatches(PdfExportContract $export, ?Model $source): void
    {
        $expected = $export->sourceModel();

        if ($expected === null && $source !== null) {
            throw new InvalidArgumentException('Este export es global: no admite entidad dueña.');
        }

        if ($expected !== null && ! $source instanceof $expected) {
            throw new InvalidArgumentException("Este export requiere una entidad {$expected}.");
        }
    }
}
