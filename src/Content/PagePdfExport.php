<?php

namespace Edc\Core\Content;

use Edc\Core\Content\Models\Page;
use Edc\Core\Pdf\PdfExport;
use Illuminate\Database\Eloquent\Model;

/**
 * PDF de una página del CRM (doc 03 + doc 02): imprime los bloques marcados
 * como imprimibles con la vista motor::pdf.page (texto maquetado, no rejilla
 * de imágenes). El motor lo registra como export 'pages'; las páginas
 * disponibles son las publicadas con is_printable.
 */
class PagePdfExport extends PdfExport
{
    public function sourceModel(): ?string
    {
        return Page::class;
    }

    /** Sin ítems-imagen: la vista propia renderiza los bloques. */
    public function items(?Model $source, string $locale): array
    {
        return [];
    }

    public function sources(string $locale): array
    {
        return Page::query()
            ->published()
            ->where('is_printable', true)
            ->orderBy('order')
            ->get()
            ->map(fn (Page $page) => [
                'id' => $page->id,
                'label' => $page->getTranslation('title', $locale) ?: "#{$page->id}",
            ])
            ->all();
    }

    public function filename(?Model $source, string $locale): string
    {
        return 'page-'.($source?->getTranslation('slug', $locale) ?: $source?->getKey())."-{$locale}";
    }

    public function view(): ?string
    {
        return 'motor::pdf.page';
    }
}
