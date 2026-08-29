<?php

namespace Edc\Core\Pdf;

/**
 * Optimiza las imágenes de la rejilla ANTES de dárselas a DomPDF: reescala
 * a la resolución de impresión (motor.pdf.print_dpi, 300 por defecto — el
 * estándar de imprenta) y APLANA la transparencia sobre blanco (el papel)
 * re-codificando a JPEG. La ruta del canal alfa de los PNG en DomPDF se
 * procesa píxel a píxel en PHP: una página 3x3 de previews de carta (PNG
 * alfa a 1500x2100) tarda ~11,5 s; la misma página con JPEG a 300 dpi,
 * ~0,1 s (x100) — era lo que ponía los PDF recortables en ~5 minutos por
 * documento. Nunca se amplía (una preview menor que el objetivo solo se
 * aplana) y cualquier fallo devuelve la imagen original: peor rendimiento,
 * mismo resultado. Con print_dpi a 0 queda desactivado del todo.
 *
 * Los temporales viven BAJO storage/app (dentro del chroot de DomPDF, que
 * es base_path()) y se borran en cleanup().
 */
class PrintImageOptimizer
{
    /** @var array<string, string> conversión por imagen fuente (las copias reutilizan) */
    protected array $converted = [];

    /** @var string[] */
    protected array $tempFiles = [];

    public function __construct(
        protected readonly PrintLayout $layout,
        protected readonly int $dpi = 300,
        protected readonly int $quality = 92,
    ) {}

    /** Ruta optimizada para DomPDF (o la entrada tal cual si no procede). */
    public function optimize(?string $source): ?string
    {
        // data-URIs (p. ej. tokens SVG), URLs remotas o dpi 0: tal cual.
        if ($source === null || $this->dpi <= 0 || ! is_file($source)) {
            return $source;
        }

        return $this->converted[$source] ??= $this->convert($source) ?? $source;
    }

    protected function convert(string $source): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $src = match (strtolower(pathinfo($source, PATHINFO_EXTENSION))) {
            'png' => @imagecreatefrompng($source),
            'jpg', 'jpeg' => @imagecreatefromjpeg($source),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };

        if ($src === false) {
            return null;
        }

        [$sw, $sh] = [imagesx($src), imagesy($src)];
        // Píxeles que ocupa la pieza a la resolución de impresión…
        $tw = (int) round($this->layout->itemWidth / 25.4 * $this->dpi);
        $th = (int) round($this->layout->itemHeight / 25.4 * $this->dpi);
        // …sin ampliar jamás: el objetivo se encaja dentro de la fuente.
        $scale = min(1.0, $tw / $sw, $th / $sh);
        $tw = max(1, (int) round($sw * $scale));
        $th = max(1, (int) round($sh * $scale));

        $dst = imagecreatetruecolor($tw, $th);
        // La transparencia se aplana a blanco: sobre papel es lo mismo.
        imagefill($dst, 0, 0, (int) imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $sw, $sh);
        imagedestroy($src);

        $dir = storage_path('app/pdf-print-tmp');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $path = $dir.'/'.uniqid('img-', true).'.jpg';
        $ok = imagejpeg($dst, $path, $this->quality);
        imagedestroy($dst);

        if (! $ok) {
            return null;
        }

        $this->tempFiles[] = $path;

        return $path;
    }

    /** Borra los temporales de esta composición. */
    public function cleanup(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];
        $this->converted = [];
    }
}
