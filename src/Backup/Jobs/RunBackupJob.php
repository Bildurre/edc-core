<?php

namespace Edc\Core\Backup\Jobs;

use Edc\Core\Backup\MotorBackup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
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

    /** El zip puede tardar: margen holgado antes de matar el job. */
    public int $timeout = 900;

    public function __construct(public ?string $filename = null) {}

    public function handle(): void
    {
        try {
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
