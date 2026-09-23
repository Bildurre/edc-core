<?php

namespace Edc\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Borra las carpetas de media HUÉRFANAS del disco público (doc 07): con el
 * MotorPathGenerator cada fichero vive en {modelo}/{id}/{mediaId}/…, así que
 * una carpeta cuyo mediaId no existe en la tabla `media` (o existe para otro
 * registro) es basura: imágenes sustituidas, registros borrados, o el
 * storage que sobrevive a una restauración de BBDD hecha sin él. Solo mira
 * dentro de las carpetas de modelos que tienen media (nunca previews, PDF,
 * contenido del CRM ni nada que no siga el patrón). --dry-run solo lista.
 */
class MediaPruneCommand extends Command
{
    protected $signature = 'motor:media:prune {--dry-run : Solo lista lo que borraría}';

    protected $description = 'Borra las carpetas de media sin registro en la tabla media.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk(config('motor.disk', 'public'));
        $mediaModel = config('media-library.media_model', Media::class);

        // Carpetas de modelo con media: strtolower(class_basename(model_type)),
        // como las escribe MotorPathGenerator.
        $models = $mediaModel::query()->distinct()->pluck('model_type')
            ->map(fn (string $type) => strtolower(class_basename($type)))
            ->unique()
            ->values();

        $orphans = [];
        foreach ($models as $model) {
            foreach ($disk->directories($model) as $modelDir) {
                $id = basename($modelDir);
                if (! ctype_digit($id)) {
                    continue;
                }

                foreach ($disk->directories($modelDir) as $mediaDir) {
                    $mediaId = basename($mediaDir);
                    if (! ctype_digit($mediaId)) {
                        continue;
                    }

                    $exists = $mediaModel::query()
                        ->whereKey((int) $mediaId)
                        ->where('model_id', (int) $id)
                        ->get()
                        ->contains(fn (Media $media) => strtolower(class_basename($media->model_type)) === $model);

                    if (! $exists) {
                        $orphans[] = $mediaDir;
                    }
                }
            }
        }

        foreach ($orphans as $directory) {
            $this->line(($dryRun ? '  (huérfana) ' : '  borrada ').$directory);
            if (! $dryRun) {
                $disk->deleteDirectory($directory);
                $this->dropIfEmpty($disk, dirname($directory));
            }
        }

        $count = count($orphans);
        $this->info($dryRun
            ? "{$count} carpetas de media huérfanas (no se ha borrado nada: --dry-run)."
            : "{$count} carpetas de media huérfanas borradas.");

        return self::SUCCESS;
    }

    /** La carpeta del registro ({modelo}/{id}) se va si se ha quedado vacía. */
    protected function dropIfEmpty(Filesystem $disk, string $directory): void
    {
        if ($disk->files($directory) === [] && $disk->directories($directory) === []) {
            $disk->deleteDirectory($directory);
        }
    }
}
