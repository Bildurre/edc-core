<?php

namespace Bgm\Core\Pdf;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Base cómoda para los exports del juego: layout por defecto de la config,
 * rejilla genérica del motor y nombre de fichero razonable. Cada export
 * concreto solo tiene que implementar sourceModel() e items().
 */
abstract class PdfExport implements PdfExportContract
{
    public function layout(): string
    {
        return config('motor.pdf.default_layout', 'card');
    }

    public function filename(?Model $source, string $locale): string
    {
        $parts = array_filter([
            $source ? Str::kebab(class_basename($source)) : null,
            $source?->getKey(),
            $locale,
        ]);

        return implode('-', $parts) ?: $locale;
    }

    public function view(): ?string
    {
        return null; // rejilla genérica del motor
    }
}
