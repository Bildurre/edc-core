<?php

namespace Edc\Core\Backup\Jobs;

use Edc\Core\Backup\MotorBackup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Spatie\Backup\Config\Config;

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

    /** El zip puede tardar: margen holgado antes de matar el job. */
    public int $timeout = 900;

    public function __construct(
        public ?string $filename = null,
        /** Mete el storage (imágenes y archivos) en ESTA copia manual. */
        public bool $includeMedia = false,
    ) {}

    public function handle(): void
    {
        try {
            // La config de spatie se aplica en el boot SIN media; si esta
            // copia manual lo pide, se reaplica incluyéndolo solo aquí.
            // isset: un job encolado con una versión anterior (sin la
            // propiedad) se deserializa con ella sin inicializar.
            if (isset($this->includeMedia) && $this->includeMedia) {
                MotorBackup::applyConfig(includeMedia: true);

                // spatie/laravel-backup v10 congela config('backup') en un
                // Config `scoped` en su primera resolución: hay que olvidarlo
                // para que backup:run se reconstruya con la config recién
                // aplicada (si no, el include del storage no le llega).
                app()->forgetInstance(Config::class);
            }

            $options = ['--disable-notifications' => true];

            // isset: un job encolado con una versión anterior (sin filename)
            // se deserializa con la propiedad sin inicializar.
            if (isset($this->filename) && $this->filename !== null) {
                $options['--filename'] = $this->filename;
            }

            Artisan::call('backup:run', $options);
        } finally {
            // Acabe bien o mal, la vista deja de sondear (si el worker muere
            // sin llegar aquí, el TTL del flag lo limpia solo).
            Cache::forget(MotorBackup::PENDING_CACHE_KEY);
        }
    }
}
