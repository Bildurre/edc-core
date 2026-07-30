<?php

namespace Edc\Core\Media\Concerns;

use Edc\Core\Support\PublicUrl;
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
        // Sobre el host de la petición (PublicUrl): el disco 'public'
        // construye la URL con APP_URL, que puede no coincidir con el
        // host/puerto real por el que llega la petición.
        return PublicUrl::onRequestHost($this->getFirstMediaUrl('image'));
    }

    /**
     * Aplica la imagen que viaje en la petición de guardado (clave 'image'):
     * fichero nuevo => sustituye (la colección singleFile borra la anterior
     * del disco); 'remove_image' verdadero sin fichero => la quita. Los
     * formularios difieren la imagen hasta el submit, así que todo cambio
     * (subir o quitar) llega aquí SOLO al guardar.
     */
    public function setImageFromRequest(Request $request, string $key = 'image'): void
    {
        if ($request->hasFile($key)) {
            $this->addMediaFromRequest($key)->toMediaCollection('image');
        } elseif ($request->boolean("remove_{$key}")) {
            $this->clearMediaCollection('image');
        }
    }
}
