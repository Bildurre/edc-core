<?php

namespace Edc\Core\Pdf;

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

    /**
     * Nombre legible del PDF en el locale pedido (card de Descargas y
     * Content-Disposition): con entidad dueña, su nombre/título traducible;
     * sin ella, la etiqueta por locale que declare el export (labels()) o,
     * como última bala, el filename embellecido (guiones → espacios,
     * mayúscula inicial, sin el sufijo de idioma).
     */
    public function displayName(?Model $source, string $locale): string
    {
        if ($source !== null) {
            return $this->sourceLabel($source, $locale);
        }

        return $this->labels()[$locale]
            ?? $this->prettifyFilename($this->filename($source, $locale), $locale);
    }

    /**
     * Etiquetas por locale para exports SIN entidad dueña (catálogos,
     * contadores...): ['es' => 'Contadores recortables', 'en' => '...'].
     * Cada juego las declara en su export; un locale sin etiqueta cae al
     * filename embellecido.
     *
     * @return array<string, string>
     */
    protected function labels(): array
    {
        return [];
    }

    /** Filename embellecido: sin sufijo de idioma, guiones a espacios, mayúscula inicial. */
    protected function prettifyFilename(string $filename, string $locale): string
    {
        $base = preg_replace('/[-_]'.preg_quote($locale, '/').'$/', '', $filename) ?: $filename;

        return Str::ucfirst(trim(str_replace(['-', '_'], ' ', $base)));
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
