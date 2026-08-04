<?php

namespace Edc\Core\Pdf;

use DOMDocument;
use Edc\Core\Site\SiteSettings;
use Illuminate\Support\Facades\Storage;

/**
 * Recursos embebidos del PDF de páginas del CRM (motor::pdf.page): DomPDF no
 * navega — todo lo que no viaje DENTRO del HTML (fuentes, imágenes) se pierde.
 *
 * - FUENTES: las tres fuentes configuradas del sitio (títulos / cuerpo /
 *   especial, doc 10) se embeben en base64 como @font-face. DomPDF solo traga
 *   TTF/OTF/WOFF (nunca WOFF2): para cada fichero configurado se busca un
 *   hermano con el mismo nombre y extensión imprimible (.ttf/.otf/.woff) en
 *   las mismas ubicaciones que sirve GET /api/site/fonts/{path} (public/fonts
 *   del API y fonts/ del disco del motor). Si una familia no tiene ningún
 *   fichero utilizable, el CSS cae con elegancia a la pila del sistema
 *   (serif para títulos/especial, sans para el cuerpo) — exactamente el
 *   aspecto de los PDF históricos de CDL, donde la decorativa no embebía.
 *
 * - IMÁGENES: las URLs de los campos image y del wysiwyg se convierten a
 *   data: URIs leyendo el fichero del disco del motor (o de public/); las
 *   irresolubles se quitan (mejor sin imagen que con el icono roto de DomPDF).
 */
class PdfPageAssets
{
    /** Extensiones que DomPDF sabe registrar, por orden de preferencia. */
    protected const FONT_EXTENSIONS = ['ttf', 'otf', 'woff'];

    /** Caché por petición de los data: URIs de fuentes (path => uri). */
    protected array $fontCache = [];

    public function __construct(protected SiteSettings $settings) {}

    /**
     * Fuentes del documento por rol. Cada rol lleva una familia CSS propia
     * (pdf-headings/pdf-body/pdf-special) con su pila de reserva: si no se
     * pudo embeber ninguna cara, la familia no existe y DomPDF usa la
     * reserva — sin errores ni cajas vacías.
     *
     * @return array<string, array{family: string, fallback: string, faces: array<int, array{src: string, weight: string, style: string}>}>
     */
    public function fonts(): array
    {
        $site = $this->settings->get();
        $catalog = $this->settings->fonts();

        $roles = [
            'headings' => ['key' => $site['font_headings'] ?? 'system', 'fallback' => 'serif'],
            // DejaVu Sans: la sans que DomPDF trae embebida (acentos seguros).
            'body' => ['key' => $site['font_body'] ?? 'system', 'fallback' => "'DejaVu Sans', sans-serif"],
            'special' => ['key' => $site['font_special'] ?? 'system', 'fallback' => 'serif'],
        ];

        $out = [];
        foreach ($roles as $role => $config) {
            $out[$role] = [
                'family' => "pdf-{$role}",
                'fallback' => $config['fallback'],
                'faces' => $this->faces($catalog[$config['key']] ?? null),
            ];
        }

        // DomPDF cachea las fuentes registradas en su font_dir (por defecto
        // storage/fonts, que Laravel no trae de fábrica): si falta, registrar
        // un @font-face revienta — se crea aquí antes de emitir ninguna cara
        // (solo si de verdad hay caras que registrar).
        $fontDir = config('dompdf.options.font_dir') ?: storage_path('fonts');
        if (array_filter(array_column($out, 'faces')) !== [] && ! is_dir($fontDir)) {
            @mkdir($fontDir, 0755, true);
        }

        return $out;
    }

    /**
     * Caras embebibles de una familia del catálogo: por cada fichero
     * configurado se resuelve un fichero imprimible, se normalizan pesos
     * (los rangos variables «100 900» se materializan en 400 y 700) y, si
     * la familia no trae negrita, la cara normal se registra también como
     * 700 — así los títulos en negrita no se caen de la fuente decorativa.
     *
     * @return array<int, array{src: string, weight: string, style: string}>
     */
    protected function faces(?array $family): array
    {
        $faces = [];

        foreach ($family['files'] ?? [] as $file) {
            $path = $this->printableFontPath($this->fontRelativePath($file['src'] ?? ''));
            if ($path === null) {
                continue;
            }

            $uri = $this->fontCache[$path] ??= $this->dataUri($path);
            $style = ($file['style'] ?? 'normal') === 'italic' ? 'italic' : 'normal';

            foreach ($this->normalizeWeights($file['weight'] ?? '400') as $weight) {
                // La primera cara declarada para (peso, estilo) gana.
                if (! isset($faces["{$weight}-{$style}"])) {
                    $faces["{$weight}-{$style}"] = [
                        'src' => $uri, 'weight' => $weight, 'style' => $style,
                    ];
                }
            }
        }

        // Sin negrita propia: la cara normal cubre el 700 (DomPDF no
        // sintetiza y caería a la pila de reserva a mitad de título).
        foreach (['normal', 'italic'] as $style) {
            if (isset($faces["400-{$style}"]) && ! isset($faces["700-{$style}"])) {
                $faces["700-{$style}"] = ['weight' => '700'] + $faces["400-{$style}"];
            }
        }

        // Sin cursiva propia (p. ej. una caligráfica como la especial de
        // CDL): la cara recta cubre también el italic — mejor la fuente
        // configurada tal cual que caer a la Times cursiva de reserva.
        foreach (['400', '700'] as $weight) {
            if (isset($faces["{$weight}-normal"]) && ! isset($faces["{$weight}-italic"])) {
                $faces["{$weight}-italic"] = ['style' => 'italic'] + $faces["{$weight}-normal"];
            }
        }

        return array_values($faces);
    }

    /** Pesos CSS concretos de una declaración: «700» => [700]; «100 900» (variable) => [400, 700]. */
    protected function normalizeWeights(string $weight): array
    {
        $bounds = array_values(array_filter(preg_split('/\s+/', trim($weight)) ?: [], 'is_numeric'));

        if (count($bounds) < 2) {
            return [is_numeric($bounds[0] ?? null) ? (string) (int) $bounds[0] : '400'];
        }

        [$min, $max] = [(int) $bounds[0], (int) $bounds[1]];

        return array_values(array_unique([
            (string) max($min, min(400, $max)),
            (string) max($min, min(700, $max)),
        ]));
    }

    /** Ruta relativa del fichero de fuente a partir de la URL del catálogo (/api/site/fonts/...). */
    protected function fontRelativePath(string $src): string
    {
        $path = parse_url($src, PHP_URL_PATH) ?: $src;

        return ltrim(preg_replace('#^.*?/api/site/fonts/#', '', $path) ?? '', '/');
    }

    /**
     * Fichero de fuente imprimible: el propio si ya es TTF/OTF/WOFF o, para
     * un WOFF2, un hermano con el mismo nombre y extensión imprimible.
     * Busca donde busca el servidor de fuentes: public/fonts y el disco.
     */
    protected function printableFontPath(string $relative): ?string
    {
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $base = preg_replace('/\.[a-z0-9]+$/i', '', $relative);
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        $candidates = in_array($extension, self::FONT_EXTENSIONS, true)
            ? [$relative]
            : array_map(fn (string $ext) => "{$base}.{$ext}", self::FONT_EXTENSIONS);

        $disk = Storage::disk(config('motor.storage.disk', 'public'));
        foreach ($candidates as $candidate) {
            if (is_file(public_path("fonts/{$candidate}"))) {
                return public_path("fonts/{$candidate}");
            }
            if ($disk->exists("fonts/{$candidate}")) {
                return $disk->path("fonts/{$candidate}");
            }
        }

        return null;
    }

    /** Imagen de un campo image (URL pública) como data: URI, o null si no está en disco. */
    public function imageDataUri(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }
        if (str_starts_with($url, 'data:')) {
            return $url;
        }

        $path = ltrim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        // URL del disco del motor (/storage/...): se lee del propio disco.
        $disk = Storage::disk(config('motor.storage.disk', 'public'));
        $relative = preg_replace('#^storage/#', '', $path);
        if ($relative && $disk->exists($relative)) {
            return $this->dataUri($disk->path($relative), image: true);
        }

        // Última bala: un fichero estático del public/ del API.
        return is_file(public_path($path)) ? $this->dataUri(public_path($path), image: true) : null;
    }

    /**
     * Embebe las imágenes de un HTML de wysiwyg (iconos rt-icon incluidos);
     * las que no se resuelven a fichero local se eliminan del marcado.
     */
    public function inlineImages(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return (string) preg_replace_callback(
            '/<img[^>]*\ssrc=["\']([^"\']+)["\'][^>]*>/i',
            function (array $match) {
                $uri = $this->imageDataUri($match[1]);

                return $uri === null ? '' : str_replace($match[1], $uri, $match[0]);
            },
            $html,
        );
    }

    /**
     * Separa los títulos INICIALES de un HTML de wysiwyg del resto del
     * contenido (portado del viejo CDL): al flotar la imagen del bloque, un
     * título con clear:both la empujaría abajo — extraído, el título queda
     * arriba a todo el ancho y la imagen flota junto al texto de verdad.
     *
     * @return array{0: string, 1: string} [títulos, resto]
     */
    public function splitLeadingHeadings(?string $html): array
    {
        if ($html === null || trim($html) === '') {
            return ['', ''];
        }

        $doc = new DOMDocument;
        $loaded = @$doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="__root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NONET,
        );
        $root = $loaded ? $doc->getElementById('__root') : null;
        if ($root === null) {
            return ['', $html];
        }

        $headings = '';
        $rest = '';
        $leading = true;
        foreach ($root->childNodes as $node) {
            $isElement = $node->nodeType === XML_ELEMENT_NODE;
            if ($leading && $isElement && preg_match('/^h[1-6]$/i', $node->nodeName)) {
                $headings .= $doc->saveHTML($node);

                continue;
            }
            if ($isElement || trim((string) $node->textContent) !== '') {
                $leading = false;
            }
            $rest .= $doc->saveHTML($node);
        }

        return [$headings, $rest];
    }

    /** Contenido de un fichero local como data: URI. */
    protected function dataUri(string $path, bool $image = false): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $image
            ? match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                'avif' => 'image/avif',
                default => 'image/png',
            }
        : "font/{$extension}";

        return "data:{$mime};base64,".base64_encode((string) file_get_contents($path));
    }
}
