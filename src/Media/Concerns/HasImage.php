<?php

namespace Edc\Core\Media\Concerns;

use Illuminate\Http\Request;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Imagen única para una entidad (colección 'image'). La entidad debe implementar
 * Spatie\MediaLibrary\HasMedia. Para imágenes por idioma usaremos un trait
 * aparte cuando el CRM lo necesite (doc 07).
 */
trait HasImage
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    public function imageUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('image');
        if ($url === '') {
            return null;
        }

        // El disco 'public' construye la URL con APP_URL, que puede no coincidir
        // con el host/puerto real por el que llega la petición (p. ej. servir en
        // :8010 con APP_URL en :8000). Reconstruimos sobre el host de la petición
        // para que la imagen sea siempre accesible. En CLI (PDF/preview) no hay
        // petición y url() recae en APP_URL, que es lo correcto allí.
        return url(parse_url($url, PHP_URL_PATH));
    }

    /** Sube la imagen si viene en la petición (clave 'image'). */
    public function setImageFromRequest(Request $request, string $key = 'image'): void
    {
        if ($request->hasFile($key)) {
            $this->addMediaFromRequest($key)->toMediaCollection('image');
        }
    }
}
