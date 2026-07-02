<?php

namespace Bgm\Core\Previews\Concerns;

use Bgm\Core\Previews\Jobs\GeneratePreviewJob;
use Bgm\Core\Previews\PreviewService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Rutas, URLs e invalidación de las previews PNG de una entidad (doc 01).
 * Requiere la columna JSON nullable `preview_image` (ruta por locale) y que
 * el modelo implemente PreviewableContract y esté en el PreviewRegistry.
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

    /** Ruta relativa del PNG del locale (en el disco de previews), o null. */
    public function previewPath(string $locale): ?string
    {
        return $this->preview_image[$locale] ?? null;
    }

    public function hasPreview(string $locale): bool
    {
        return $this->previewPath($locale) !== null;
    }

    /** URL pública del PNG del locale, o null si no está generado. */
    public function previewUrl(string $locale): ?string
    {
        $path = $this->previewPath($locale);

        return $path ? Storage::disk(config('motor.previews.disk'))->url($path) : null;
    }

    /** Mapa locale => URL (solo los generados). */
    public function previewUrls(): array
    {
        $urls = [];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            if ($url = $this->previewUrl($locale)) {
                $urls[$locale] = $url;
            }
        }

        return $urls;
    }

    /** Encola (o ejecuta, con $sync) la regeneración por locale. */
    public function regeneratePreviews(?array $locales = null, bool $sync = false): void
    {
        if (! config('motor.previews.enabled', true)) {
            return;
        }

        $locales ??= array_keys(config('motor.locales', []));

        foreach ($locales as $locale) {
            $sync
                ? GeneratePreviewJob::dispatchSync(static::class, $this->getKey(), $locale)
                : GeneratePreviewJob::dispatch(static::class, $this->getKey(), $locale);
        }
    }

    /** Borra los PNG del disco y limpia la columna (sin disparar eventos). */
    public function deletePreviews(): void
    {
        app(PreviewService::class)->deleteFor($this);
    }
}
