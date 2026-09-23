<?php

namespace Edc\Core\Backup\Jobs;

use Edc\Core\Backup\MotorBackup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Copia de seguridad en cola (doc 06, DC-16): el POST del admin no espera al
 * zip; el worker la crea y la vista sondea el listado (flag `pending`). El
 * nombre viaja con el job (prefijo `manual-`) para distinguir el origen de
 * la copia en el listado.
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** El zip puede tardar (con el storage, GBs): margen holgado antes de matar el job. */
    public int $timeout = 3600;

    public function __construct(
        public ?string $filename = null,
        /** Mete el storage (imágenes y archivos) en ESTA copia manual. */
        public bool $includeMedia = false,
    ) {}

    public function handle(): void
    {
        try {
            // La manual decide por sí misma si lleva el storage: si la config
            // de spatie VIGENTE (la del último applyConfig de este proceso:
            // el boot, con el ajuste de la automática, o un job anterior)
            // no es la suya, se reaplica con su elección. Antes se comparaba
            // con el ajuste de la automática, y en un worker de larga vida
            // tras una copia con imágenes la siguiente sin ellas (automática
            // también sin) salía con el storage dentro. isset: un job
            // encolado con una versión anterior (sin la propiedad) se
            // deserializa sin inicializar.
            $withMedia = isset($this->includeMedia) && $this->includeMedia;

            if (MotorBackup::appliedWithMedia() !== $withMedia) {
                MotorBackup::applyConfig(includeMedia: $withMedia);
            }

            // Copia construida de la config VIGENTE (MotorBackup::run): nada
            // de `backup:run`, cuyo Config inyectado es el del boot del
            // worker. isset: un job encolado con una versión anterior (sin
            // filename) se deserializa con la propiedad sin inicializar.
            MotorBackup::run(isset($this->filename) ? $this->filename : null);
        } finally {
            // Acabe bien o mal, la vista deja de sondear (si el worker muere
            // sin llegar aquí, el TTL del flag lo limpia solo).
            Cache::forget(MotorBackup::PENDING_CACHE_KEY);
        }
    }
}
