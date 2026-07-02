<?php

namespace Bgm\Core\Pdf;

use InvalidArgumentException;

/**
 * Registro de exports de PDF. Cada juego registra los suyos en el boot de su
 * AppServiceProvider:
 *
 *     Pdfs::register('house-schemes', HouseSchemesExport::class);
 *
 * La clave es el `type` del GeneratedPdf y de la API (/admin/pdfs).
 */
class PdfExportRegistry
{
    /** @var array<string, class-string<PdfExportContract>> */
    protected array $exports = [];

    /** @param class-string<PdfExportContract> $exportClass */
    public function register(string $type, string $exportClass): void
    {
        if (! is_subclass_of($exportClass, PdfExportContract::class)) {
            throw new InvalidArgumentException(
                "{$exportClass} debe implementar ".PdfExportContract::class
            );
        }

        $this->exports[$type] = $exportClass;
    }

    public function has(string $type): bool
    {
        return isset($this->exports[$type]);
    }

    /** @return string[] */
    public function types(): array
    {
        return array_keys($this->exports);
    }

    public function get(string $type): PdfExportContract
    {
        if (! $this->has($type)) {
            throw new InvalidArgumentException("Export de PDF desconocido: {$type}");
        }

        return app($this->exports[$type]);
    }
}
