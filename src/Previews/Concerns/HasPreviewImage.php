<?php

namespace Edc\Core\Previews\Concerns;

use Edc\Core\Previews\Jobs\GeneratePreviewJob;
use Edc\Core\Previews\PreviewRegistry;
use Edc\Core\Previews\PreviewService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Rutas, URLs e invalidación de las previews PNG de una entidad (doc 01).
 * Requiere la columna JSON nullable `preview_image` y que el modelo
 * implemente PreviewableContract y esté en el PreviewRegistry.
 *
 * La columna guarda un mapa clave-de-preview => (locale => ruta): un modelo
 * registrado bajo varias claves tiene varias previews independientes. Los
 * métodos aceptan la clave ($type); null = la por defecto (la primera).
 * Toda la lógica de render vive fuera (PreviewService), no en el trait.
 */
trait HasPreviewImage
{
    public function initializeHasPreviewImage(): void
    {
        $this->mergeCasts(['preview_image' => 'array']);
    }

    public static function bootHasPreviewImage(): void
    {
        // Invalidación automática declarativa: solo si cambia algún campo de
        // previewTriggerFields() (editar is_published, p. ej., no regenera).
        static::created(function (Model $model) {
            $model->regeneratePreviews();
        });

        static::updated(function (Model $model) {
            $dirty = array_keys($model->getChanges());
            if (array_intersect($model->previewTriggerFields(), $dirty) !== []) {
                $model->regeneratePreviews();
            }
        });

        // Al borrar de verdad (o borrar sin soft-delete), fuera los PNG.
        static::deleted(function (Model $model) {
            $soft = in_array(SoftDeletes::class, class_uses_recursive($model), true);
            if (! $soft || $model->isForceDeleting()) {
                $model->deletePreviews();
            }
        });
    }

    /** Clave de preview por defecto del modelo (la primera registrada). */
    public function defaultPreviewType(): ?string
    {
        return app(PreviewRegistry::class)->keyFor($this);
    }

    /** Ruta relativa del PNG (disco de previews) del locale, o null. */
    public function previewPath(string $locale, ?string $type = null): ?string
    {
        $type ??= $this->defaultPreviewType();

        return $this->preview_image[$type][$locale] ?? null;
    }

    public function hasPreview(string $locale, ?string $type = null): bool
    {
        return $this->previewPath($locale, $type) !== null;
    }

    /** URL pública del PNG del locale, o null si no está generado. */
    public function previewUrl(string $locale, ?string $type = null): ?string
    {
        $path = $this->previewPath($locale, $type);

        return $path ? Storage::disk(config('motor.previews.disk'))->url($path) : null;
    }

    /** Mapa locale => URL de una preview (solo los generados). */
    public function previewUrls(?string $type = null): array
    {
        $urls = [];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            if ($url = $this->previewUrl($locale, $type)) {
                $urls[$locale] = $url;
            }
        }

        return $urls;
    }

    /**
     * Encola (o ejecuta, con $sync) la regeneración por locale. Por defecto
     * regenera TODAS las previews del modelo (todas sus claves registradas).
     */
    public function regeneratePreviews(?array $locales = null, bool $sync = false, ?array $types = null): void
    {
        if (! config('motor.previews.enabled', true)) {
            return;
        }

        $locales ??= array_keys(config('motor.locales', []));
        $types ??= app(PreviewRegistry::class)->keysFor($this);

        foreach ($types as $type) {
            foreach ($locales as $locale) {
                $sync
                    ? GeneratePreviewJob::dispatchSync(static::class, $this->getKey(), $locale, $type)
                    : GeneratePreviewJob::dispatch(static::class, $this->getKey(), $locale, $type);
            }
        }
    }

    /** Borra los PNG del disco y limpia la columna (sin disparar eventos). */
    public function deletePreviews(?string $type = null): void
    {
        app(PreviewService::class)->deleteFor($this, $type);
    }
}
