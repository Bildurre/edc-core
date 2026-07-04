<?php

namespace Bgm\Core\Site\Http\Controllers;

use Bgm\Core\Site\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Configuración de la web (doc 10): GET público (la SPA la aplica al
 * arrancar) y GET/PUT de admin. Todos los campos son públicos por diseño:
 * aquí no vive nada sensible.
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
        $fonts = array_keys($this->settings->fonts());

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
            'font_headings' => ['sometimes', 'in:'.implode(',', $fonts)],
            'font_body' => ['sometimes', 'in:'.implode(',', $fonts)],
            'footer_text' => ['sometimes', 'array'],
            'footer_text.*' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json(['data' => [...$this->settings->update($data), 'fonts' => $this->settings->fonts()]]);
    }
}
