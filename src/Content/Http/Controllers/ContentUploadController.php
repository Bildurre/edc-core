<?php

namespace Edc\Core\Content\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Subida de imágenes del CRM y la configuración (campos `image`): guarda en
 * el disco del motor CON EL NOMBRE ORIGINAL del fichero (saneado; con sufijo
 * -2, -3… si ya existe otro) y devuelve la URL pública, que es lo que se
 * almacena. Si la petición trae `replaces` (la URL del fichero al que
 * sustituye), el anterior se borra: sin huérfanos con nombres raros.
 */
class ContentUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // allow_svg: la regla image moderna excluye SVG por defecto y el
            // logo/los fondos lo necesitan. El contenido se sanea abajo.
            'image' => ['required', 'image:allow_svg', 'max:10240'],
            'replaces' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        $disk = config('motor.storage.disk', 'public');
        $file = $request->file('image');

        // Nombre original saneado: "Mi Logo (v2).SVG" → mi-logo-v2.svg
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'imagen';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        // El fichero sustituido se borra ANTES de guardar: si se llama igual,
        // el nombre queda libre y no hace falta sufijo.
        $this->deleteManaged($request->input('replaces'));

        $filename = "{$base}.{$extension}";
        for ($i = 2; Storage::disk($disk)->exists("content/{$filename}"); $i++) {
            $filename = "{$base}-{$i}.{$extension}";
        }

        if ($extension === 'svg') {
            // El SVG puede acabar inlineado en la web (logo): se guarda ya
            // saneado — sin scripts, handlers on*, javascript: ni foreignObject.
            Storage::disk($disk)->put("content/{$filename}", $this->sanitizeSvg($file->get()));
            $path = "content/{$filename}";
        } else {
            $path = $file->storeAs('content', $filename, $disk);
        }

        return response()->json([
            'url' => Storage::disk($disk)->url($path),
            'path' => $path,
        ], 201);
    }

    /** Saneado básico de SVG: fuera vectores de ejecución, queda el dibujo. */
    protected function sanitizeSvg(string $svg): string
    {
        $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg);
        $svg = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $svg);
        $svg = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $svg);
        $svg = preg_replace('#\s(?:xlink:)?href\s*=\s*("javascript:[^"]*"|\'javascript:[^\']*\')#i', '', $svg);

        return $svg;
    }

    /** Borra una imagen subida (el botón "quitar" de los inputs de imagen). */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['url' => ['required', 'string', 'max:2048']]);

        $this->deleteManaged($request->input('url'));

        return response()->json(['ok' => true]);
    }

    /**
     * Borra el fichero si (y solo si) es una subida gestionada: dentro de
     * content/ del disco del motor, sin traversal. Cualquier otra cosa
     * (URLs externas, fonts, …) se ignora en silencio.
     */
    protected function deleteManaged(?string $url): void
    {
        if (! $url) {
            return;
        }

        $path = ltrim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        $path = preg_replace('#^storage/#', '', $path);

        if (! $path || ! str_starts_with($path, 'content/') || str_contains($path, '..')) {
            return;
        }

        Storage::disk(config('motor.storage.disk', 'public'))->delete($path);
    }
}
