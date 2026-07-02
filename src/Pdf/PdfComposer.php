<?php

namespace Bgm\Core\Pdf;

use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Bgm\Core\Pdf\Models\GeneratedPdf;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ensambla el PDF (DC-06): expande copias, pagina según el PrintLayout y
 * renderiza la vista (la rejilla genérica del motor o la propia del export)
 * con DomPDF. Guarda el fichero versionado y devuelve su ruta.
 *
 * @phpstan-type ResolvedItem array{image: string}
 */
class PdfComposer
{
    /**
     * @param  array<int, array{image: string}>  $slots  huecos YA expandidos (una imagen por hueco)
     */
    public function compose(GeneratedPdf $pdf, array $slots, PrintLayout $layout, ?string $view = null): string
    {
        $pages = array_chunk($slots, max(1, $layout->capacity()));

        $dompdf = DomPdf::loadView($view ?? 'motor::pdf.grid', [
            'pdf' => $pdf,
            'pages' => $pages,
            'layout' => $layout,
        ])->setPaper($layout->paper, $layout->orientation);

        // Nombre versionado: cada generación produce una URL nueva.
        $path = trim(config('motor.pdf.path', 'pdfs'), '/')
            ."/{$pdf->type}/".($pdf->source_id ?? 'global')
            ."/{$pdf->filename}-".Str::random(8).'.pdf';

        $this->disk()->put($path, $dompdf->output());

        return $path;
    }

    /**
     * Expande PrintableItems (copias) a huecos con la imagen resuelta.
     *
     * @param  PrintableItem[]  $items
     * @param  callable(PrintableItem): ?string  $resolveImage  imagen del ítem (o null si no hay)
     * @return array<int, array{image: string}>
     */
    public function expand(array $items, callable $resolveImage): array
    {
        $slots = [];

        foreach ($items as $item) {
            $image = $resolveImage($item);
            if ($image === null) {
                continue;
            }
            for ($i = 0; $i < $item->copies; $i++) {
                $slots[] = ['image' => $image];
            }
        }

        return $slots;
    }

    protected function disk(): Filesystem
    {
        return Storage::disk(config('motor.pdf.disk'));
    }
}
