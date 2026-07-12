<?php

namespace Edc\Core\Previews;

use Edc\Core\Support\Concerns\HasPublishedState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Ítem del catálogo público: la forma mínima compartida por el endpoint
 * /api/catalog/{key} y el bloque `related` del CRM. La tarjeta visual la
 * decide la app (preview null = sin PNG generado, sin placeholder aquí).
 */
class CatalogItem
{
    /**
     * Query base de una entidad del catálogo: solo publicadas si el modelo
     * usa HasPublishedState (detección genérica por trait).
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function query(string $modelClass): Builder
    {
        $query = $modelClass::query();

        if (in_array(HasPublishedState::class, class_uses_recursive($modelClass), true)) {
            $query->published();
        }

        return $query;
    }

    /**
     * Serializa un modelo al formato de ítem: id, name localizado, slug
     * localizado (o null si no tiene) y URL de la preview PNG (o null).
     *
     * @return array{id: int|string, name: string, slug: string|null, preview: string|null}
     */
    public static function fromModel(Model $model, string $key, string $locale): array
    {
        $translatable = method_exists($model, 'getTranslatableAttributes')
            ? $model->getTranslatableAttributes()
            : [];

        $slug = in_array('slug', $translatable, true)
            ? ($model->getTranslation('slug', $locale) ?: null)
            : null;

        return [
            'id' => $model->getKey(),
            'name' => (string) $model->getTranslation('name', $locale),
            'slug' => $slug,
            'preview' => method_exists($model, 'previewUrl')
                ? $model->previewUrl($locale, $key)
                : null,
        ];
    }
}
