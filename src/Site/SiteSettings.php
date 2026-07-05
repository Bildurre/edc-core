<?php

namespace Bgm\Core\Site;

use Bgm\Core\Site\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Configuración de la web pública (doc 10): título, logo, favicon, fuentes,
 * acento (fijo o ALEATORIO estilo CDL: una lista de colores de la que la SPA
 * sortea uno al cargar y al navegar) y pie. Se guarda como un JSON bajo la
 * clave 'site' y la SPA lo consume de GET /api/site.
 *
 * Fuentes: el catálogo vive en config `motor.site.fonts` (el juego añade las
 * suyas); cada entrada puede llevar `files` (woff2 en public/fonts del API) y
 * la SPA genera los @font-face. Además el admin puede SUBIR fuentes propias
 * (`custom_fonts`), servidas —como las del catálogo— por
 * GET /api/site/fonts/{path}, que pasa por el CORS del grupo api.
 */
class SiteSettings
{
    protected const CACHE_KEY = 'motor.settings.site';

    /** Valores por defecto: web funcional sin configurar nada. */
    public function defaults(): array
    {
        return [
            'title' => [],           // {locale: nombre del sitio}
            'description' => [],     // {locale: meta description por defecto}
            'logo' => null,          // URL (SVG/PNG)
            'favicon' => null,       // URL (PNG/SVG)
            'accent_mode' => 'fixed',
            'accent_color' => '#6c5ce7',
            'accent_colors' => [],   // candidatos del modo aleatorio
            'font_headings' => 'system',
            'font_body' => 'system',
            // Fuente "especial": acentos puntuales (por ahora, el bloque cita).
            'font_special' => 'system',
            'custom_fonts' => [],    // [{key, name, file}] subidas por el admin
            'footer_text' => [],     // {locale: texto del pie}
        ];
    }

    /** Ajustes efectivos (guardados sobre los defaults), cacheados. */
    public function get(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $saved = Setting::query()->where('key', 'site')->value('value') ?? [];

            return [...$this->defaults(), ...$saved];
        });
    }

    /** Guarda (mezclando sobre lo actual) e invalida la caché. */
    public function update(array $data): array
    {
        $value = [...$this->get(), ...$data];
        unset($value['fonts'], $value['logo_inline']);

        Setting::query()->updateOrCreate(['key' => 'site'], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);

        return $this->get();
    }

    /**
     * Catálogo de fuentes normalizado: clave => {label, stack, files[]}.
     * En config una entrada puede ser una pila CSS a secas (pilas del
     * sistema) o un array con label/stack/files (webfonts del juego).
     */
    public function fonts(): array
    {
        $fonts = [];
        foreach (config('motor.site.fonts', []) as $key => $font) {
            $fonts[$key] = is_string($font)
                ? ['label' => Str::headline($key), 'stack' => $font, 'files' => []]
                : [
                    'label' => $font['label'] ?? Str::headline($key),
                    'stack' => $font['stack'],
                    'files' => array_map(fn ($file) => [
                        'family' => $file['family'] ?? $font['label'] ?? Str::headline($key),
                        'src' => $this->fontUrl($file['src']),
                        'weight' => $file['weight'] ?? '400',
                        'style' => $file['style'] ?? 'normal',
                    ], $font['files'] ?? []),
                ];
        }

        // Fuentes subidas por el admin: una familia por fichero.
        foreach ($this->get()['custom_fonts'] ?? [] as $custom) {
            $fonts[$custom['key']] = [
                'label' => $custom['name'],
                'stack' => "'{$custom['name']}', system-ui, sans-serif",
                'files' => [[
                    'family' => $custom['name'],
                    'src' => $this->fontUrl($custom['file']),
                    'weight' => '100 900',
                    'style' => 'normal',
                ]],
            ];
        }

        return $fonts;
    }

    /** Claves elegibles para font_headings / font_body. */
    public function fontKeys(): array
    {
        return array_keys($this->fonts());
    }

    /** Guarda un fichero de fuente subido y devuelve su registro (+url para la vista previa). */
    public function storeFont(string $name, UploadedFile $file): array
    {
        $key = 'custom-'.Str::slug($name);
        $filename = $key.'.'.$file->getClientOriginalExtension();
        $file->storeAs('fonts', $filename, config('motor.storage.disk', 'public'));

        return ['key' => $key, 'name' => $name, 'file' => $filename, 'url' => $this->fontUrl($filename)];
    }

    /** Payload para las SPA: ajustes + catálogo de fuentes resuelto. */
    public function payload(): array
    {
        return [...$this->get(), 'fonts' => $this->fonts(), 'logo_inline' => $this->logoInline()];
    }

    /** URL de un fichero de fuente por la ruta con CORS del API. */
    protected function fontUrl(string $path): string
    {
        return url('/api/site/fonts/'.ltrim($path, '/'));
    }

    /**
     * Contenido del logo cuando es un SVG del disco del motor: la SPA lo
     * inlinea y currentColor hereda el acento (el modo aleatorio lo
     * recolorea, como los logo-path de CDL). Un fichero cross-origin no
     * serviría (fetch/mask exigen CORS); por eso viaja dentro del payload.
     */
    protected function logoInline(): ?string
    {
        $logo = $this->get()['logo'] ?? null;
        if (! $logo || ! str_ends_with($logo, '.svg')) {
            return null;
        }

        $disk = Storage::disk(config('motor.storage.disk', 'public'));
        $path = ltrim(parse_url($logo, PHP_URL_PATH) ?: '', '/');
        $path = preg_replace('#^storage/#', '', $path);

        return $path && $disk->exists($path) ? $disk->get($path) : null;
    }
}
