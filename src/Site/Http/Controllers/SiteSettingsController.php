<?php

namespace Edc\Core\Site\Http\Controllers;

use Edc\Core\Site\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * Configuración de la web (doc 10): GET público (la SPA la aplica al
 * arrancar) y GET/PUT de admin. Todos los campos son públicos por diseño:
 * aquí no vive nada sensible. Incluye la subida de fuentes propias y el
 * servido de los ficheros de fuente (catálogo y subidas) con CORS.
 */
class SiteSettingsController extends Controller
{
    public function __construct(protected SiteSettings $settings) {}

    /** Público: ajustes + catálogo de fuentes. */
    public function show()
    {
        return response()->json(['data' => $this->settings->payload()]);
    }

    /** Admin: lo mismo (el formulario edita sobre los valores efectivos). */
    public function edit()
    {
        return response()->json(['data' => $this->settings->payload()]);
    }

    public function update(Request $request)
    {
        $hex = 'regex:/^#[0-9a-fA-F]{6}$/';
        // Con custom_fonts en la MISMA petición, las claves nuevas ya valen
        // para font_headings/font_body.
        $fontKeys = collect($request->input('custom_fonts', []))
            ->pluck('key')
            ->merge($this->settings->fontKeys())
            ->implode(',');

        $data = $request->validate([
            'title' => ['sometimes', 'array'],
            'title.*' => ['nullable', 'string', 'max:120'],
            'description' => ['sometimes', 'array'],
            'description.*' => ['nullable', 'string', 'max:300'],
            'logo' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'favicon' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'accent_mode' => ['sometimes', 'in:fixed,random'],
            'accent_color' => ['sometimes', 'string', $hex],
            'accent_colors' => ['sometimes', 'array', 'max:12'],
            'accent_colors.*' => ['string', $hex],
            'font_headings' => ['sometimes', 'in:'.$fontKeys],
            'font_body' => ['sometimes', 'in:'.$fontKeys],
            'font_special' => ['sometimes', 'in:'.$fontKeys],
            'custom_fonts' => ['sometimes', 'array', 'max:12'],
            'custom_fonts.*.key' => ['required', 'string', 'max:80'],
            'custom_fonts.*.name' => ['required', 'string', 'max:80'],
            'custom_fonts.*.file' => ['required', 'string', 'max:120'],
            'footer_text' => ['sometimes', 'array'],
            'footer_text.*' => ['nullable', 'string', 'max:500'],
        ]);

        $this->settings->update($data);

        return response()->json(['data' => $this->settings->payload()]);
    }

    /** Admin: sube un fichero de fuente (woff2/woff/ttf/otf). */
    public function storeFont(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'file' => ['required', 'file', 'extensions:woff2,woff,ttf,otf', 'max:4096'],
        ]);

        return response()->json(
            ['data' => $this->settings->storeFont($data['name'], $data['file'])],
            201,
        );
    }

    /**
     * Sirve un fichero de fuente por el grupo api (hereda el CORS): primero
     * las del catálogo (public/fonts del API), luego las subidas (disco).
     */
    public function font(string $path)
    {
        abort_if(str_contains($path, '..'), 404);

        $headers = ['Cache-Control' => 'public, max-age=31536000, immutable'];

        $local = public_path('fonts/'.$path);
        if (is_file($local)) {
            return response()->file($local, $headers);
        }

        $disk = Storage::disk(config('motor.storage.disk', 'public'));
        abort_unless($disk->exists('fonts/'.$path), 404);

        return response($disk->get('fonts/'.$path), 200, [
            ...$headers,
            'Content-Type' => match (pathinfo($path, PATHINFO_EXTENSION)) {
                'woff2' => 'font/woff2',
                'woff' => 'font/woff',
                'ttf' => 'font/ttf',
                'otf' => 'font/otf',
                default => 'application/octet-stream',
            },
        ]);
    }
}
