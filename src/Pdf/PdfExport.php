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
    public function sources(string $locale): array
    {
        return [];
    }

    public function layout(): string
    {
        return config('motor.pdf.default_layout', 'card');
    }

    public function filename(?Model $source, string $locale): string
    {
        // El NOMBRE del elemento, nunca su id (la unicidad del fichero la da
        // el sufijo aleatorio del path al generar).
        $parts = array_filter([
            $source ? Str::slug($this->sourceLabel($source, $locale)) : null,
            $locale,
        ]);

        return implode('-', $parts) ?: $locale;
    }

    /** Nombre legible del elemento dueño (para el fichero). */
    protected function sourceLabel(Model $source, string $locale): string
    {
        if (method_exists($source, 'previewLabel')) {
            return (string) $source->previewLabel($locale);
        }

        foreach (['name', 'title'] as $field) {
            if (method_exists($source, 'getTranslation')) {
                $value = rescue(fn () => $source->getTranslation($field, $locale), null, report: false);
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
            $value = $source->getAttribute($field);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return Str::kebab(class_basename($source));
    }

    public function view(): ?string
    {
        return null; // rejilla genérica del motor
    }
}
