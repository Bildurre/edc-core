<?php

namespace Edc\Core\Console;

use Edc\Core\Pdf\PdfService;
use Illuminate\Console\Command;

/**
 * Borra los PDF temporales caducados (fichero + registro). Programable en el
 * scheduler del juego (routes/console.php): Schedule::command('pdf:cleanup')->daily().
 */
class PdfCleanupCommand extends Command
{
    protected $signature = 'pdf:cleanup';

    protected $description = 'Elimina los PDF temporales caducados.';

    public function handle(PdfService $service): int
    {
        $count = $service->cleanupExpired();

        $this->info("{$count} PDF temporales eliminados.");

        return self::SUCCESS;
    }
}
