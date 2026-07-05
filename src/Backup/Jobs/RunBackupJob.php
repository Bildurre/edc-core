<?php

namespace Bgm\Core\Backup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

/**
 * Copia de seguridad en cola (doc 06, DC-16): para BBDD grandes el POST del
 * admin no espera al zip; el worker la crea y la vista sondea el listado.
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** El zip puede tardar: margen holgado antes de matar el job. */
    public int $timeout = 900;

    public function handle(): void
    {
        Artisan::call('backup:run', ['--disable-notifications' => true]);
    }
}
