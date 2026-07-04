<?php

namespace Bgm\Core\Site;

use Bgm\Core\Site\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Configuración de la web pública (doc 10): título, logo, favicon, fuentes,
 * acento (fijo o ALEATORIO estilo CDL: una lista de colores de la que la SPA
 * sortea uno al cargar y al navegar) y pie. Se guarda como un JSON bajo la
 * clave 'site' y la SPA lo consume de GET /api/site.
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
        unset($value['fonts']);

        Setting::query()->updateOrCreate(['key' => 'site'], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);

        return $this->get();
    }

    /** Catálogo de fuentes (clave => pila CSS); el juego añade las suyas en config. */
    public function fonts(): array
    {
        return config('motor.site.fonts', []);
    }

    /** Payload para las SPA: ajustes + catálogo de fuentes resuelto. */
    public function payload(): array
    {
        return [...$this->get(), 'fonts' => $this->fonts(), 'logo_inline' => $this->logoInline()];
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
