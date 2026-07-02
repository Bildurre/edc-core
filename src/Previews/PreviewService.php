<?php

namespace Bgm\Core\Previews;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Orquesta el render de previews: emite el token, captura la URL /_render
 * de la SPA, guarda el PNG, borra el anterior y actualiza el modelo.
 * También limpia huérfanos y da el estado por entidad (doc 01).
 */
class PreviewService
{
    public function __construct(
        protected PreviewRegistry $registry,
        protected PreviewRenderer $renderer,
        protected RenderToken $tokens,
    ) {}

    /** Genera (o regenera) el PNG de una entidad para un locale. */
    public function generate(Model&PreviewableContract $entity, string $locale): string
    {
        $key = $this->registry->keyFor($entity);

        if ($key === null) {
            throw new InvalidArgumentException(
                $entity::class.' no está registrado en el PreviewRegistry.'
            );
        }

        $size = $entity->previewSize();
        $url = $this->renderUrl($key, $entity->getKey(), $locale);

        // Captura a un temporal y después al disco de previews.
        $tmp = tempnam(sys_get_temp_dir(), 'bgm-preview-').'.png';

        try {
            $this->renderer->capture($url, (int) $size['width'], (int) $size['height'], $tmp);

            $path = $this->storedPath($key, $entity->getKey(), $locale);
            $this->disk()->put($path, file_get_contents($tmp));
        } finally {
            @unlink($tmp);
        }

        // Borra el PNG anterior del locale (nombre versionado => URL nueva).
        $previous = $entity->previewPath($locale);
        if ($previous && $previous !== $path) {
            $this->disk()->delete($previous);
        }

        // Actualiza el modelo sin disparar eventos (no re-invalidar).
        $images = $entity->preview_image ?? [];
        $images[$locale] = $path;
        $entity->preview_image = $images;
        $entity->saveQuietly();

        return $path;
    }

    /** Borra todos los PNG de la entidad y limpia la columna. */
    public function deleteFor(Model $entity): void
    {
        $key = $this->registry->keyFor($entity);

        if ($key !== null) {
            $this->disk()->deleteDirectory($this->basePath()."/{$key}/{$entity->getKey()}");
        }

        if ($entity->exists && $entity->preview_image !== null) {
            $entity->preview_image = null;
            $entity->saveQuietly();
        }
    }

    /**
     * Elimina ficheros de previews que ya no referencia ninguna entidad
     * (huérfanos). Devuelve las rutas afectadas; con $dryRun solo las lista.
     *
     * @return string[]
     */
    public function cleanOrphans(bool $dryRun = false): array
    {
        $orphans = [];

        foreach ($this->registry->all() as $key => $modelClass) {
            $referenced = $this->referencedPaths($modelClass);

            foreach ($this->disk()->allFiles($this->basePath()."/{$key}") as $file) {
                if (! in_array($file, $referenced, true)) {
                    $orphans[] = $file;
                    if (! $dryRun) {
                        $this->disk()->delete($file);
                    }
                }
            }
        }

        return $orphans;
    }

    /** Estado por entidad registrada: total, completas, pendientes. */
    public function status(): array
    {
        $locales = array_keys(config('motor.locales', []));
        $status = [];

        foreach ($this->registry->all() as $key => $modelClass) {
            $total = 0;
            $complete = 0;

            foreach ($modelClass::query()->get() as $entity) {
                $total++;
                $missing = array_filter($locales, fn ($l) => ! $entity->hasPreview($l));
                if ($missing === []) {
                    $complete++;
                }
            }

            $status[$key] = [
                'model' => $modelClass,
                'total' => $total,
                'complete' => $complete,
                'pending' => $total - $complete,
            ];
        }

        return $status;
    }

    /** URL de la ruta /_render de la SPA, con el token de servicio (DC-04). */
    protected function renderUrl(string $key, int|string $id, string $locale): string
    {
        $base = rtrim(
            config('motor.previews.render_url') ?: config('motor.frontend.app_url'),
            '/',
        );
        $token = $this->tokens->issue($key, $id);

        return "{$base}/_render/{$key}/{$id}?".http_build_query([
            'locale' => $locale,
            'token' => $token,
        ]);
    }

    /** Ruta versionada del PNG (nombre nuevo en cada render => sin caché vieja). */
    protected function storedPath(string $key, int|string $id, string $locale): string
    {
        return $this->basePath()."/{$key}/{$id}/{$locale}-".Str::random(8).'.png';
    }

    /** @return string[] */
    protected function referencedPaths(string $modelClass): array
    {
        $query = $modelClass::query();

        if (method_exists($modelClass, 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        return $query->whereNotNull('preview_image')
            ->pluck('preview_image')
            ->flatMap(fn ($images) => array_values($images ?? []))
            ->all();
    }

    protected function disk(): Filesystem
    {
        return Storage::disk(config('motor.previews.disk'));
    }

    protected function basePath(): string
    {
        return trim(config('motor.previews.path', 'previews'), '/');
    }
}
