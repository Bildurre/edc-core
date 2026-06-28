<?php

namespace Bgm\Core\Media\Concerns;

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

        return $url !== '' ? $url : null;
    }

    /** Sube la imagen si viene en la petición (clave 'image'). */
    public function setImageFromRequest(Request $request, string $key = 'image'): void
    {
        if ($request->hasFile($key)) {
            $this->addMediaFromRequest($key)->toMediaCollection('image');
        }
    }
}
