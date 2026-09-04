<?php

namespace Edc\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reescribe un origen de URL por otro en TODO el contenido de la base de
 * datos: al importar la BD de otro entorno (local → producción) el texto
 * enriquecido, los bloques y la configuración conservan las URL absolutas
 * de las imágenes e iconos del entorno de origen (p. ej.
 * http://localhost:8010/storage/...), que en el nuevo dominio no cargan (o
 * saltan como contenido mixto en https). Recorre todas las tablas y sus
 * columnas de texto/JSON y sustituye tanto la forma plana como la escapada
 * de JSON (http:\/\/...). Sin barra final en los orígenes.
 *
 *   php artisan motor:rewrite-urls http://localhost:8010 https://mi-dominio.com
 *   php artisan motor:rewrite-urls http://localhost:8010 https://mi-dominio.com --dry-run
 */
class RewriteUrlsCommand extends Command
{
    protected $signature = 'motor:rewrite-urls
        {from : Origen a sustituir (p. ej. http://localhost:8010)}
        {to : Origen nuevo (p. ej. https://mi-dominio.com)}
        {--dry-run : Solo cuenta las filas afectadas, sin escribir}';

    protected $description = 'Sustituye un origen de URL por otro en todo el contenido de la base de datos (tras importar una BD de otro entorno).';

    /** Tablas de infraestructura que no llevan contenido. */
    protected const SKIP_TABLES = [
        'migrations', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks',
        'sessions', 'password_reset_tokens', 'personal_access_tokens',
    ];

    /** Tipos de columna con texto donde puede vivir una URL. */
    protected const TEXT_TYPES = ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext', 'json', 'string'];

    public function handle(): int
    {
        $from = rtrim((string) $this->argument('from'), '/');
        $to = rtrim((string) $this->argument('to'), '/');
        if ($from === '' || $to === '' || $from === $to) {
            $this->error('Indica dos orígenes distintos, sin barra final.');

            return self::FAILURE;
        }

        // Forma plana y forma escapada de JSON (json_encode escapa las barras).
        $pairs = [
            [$from, $to],
            [str_replace('/', '\/', $from), str_replace('/', '\/', $to)],
        ];

        $dry = (bool) $this->option('dry-run');
        $total = 0;

        foreach (Schema::getTableListing() as $table) {
            $name = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
            if (in_array($name, self::SKIP_TABLES, true)) {
                continue;
            }

            foreach (Schema::getColumns($name) as $column) {
                if (! in_array(strtolower((string) $column['type_name']), self::TEXT_TYPES, true)) {
                    continue;
                }

                foreach ($pairs as [$search, $replace]) {
                    // INSTR y no LIKE: en MySQL la barra invertida escapa el
                    // patrón de LIKE y en SQLite no, y la forma escapada de
                    // JSON lleva barras invertidas.
                    $where = sprintf('INSTR(%s, ?) > 0', $this->wrap($column['name']));
                    $count = DB::table($name)->whereRaw($where, [$search])->count();
                    if ($count === 0) {
                        continue;
                    }
                    if (! $dry) {
                        DB::table($name)
                            ->whereRaw($where, [$search])
                            ->update([$column['name'] => DB::raw(sprintf(
                                'REPLACE(%s, %s, %s)',
                                $this->wrap($column['name']),
                                DB::getPdo()->quote($search),
                                DB::getPdo()->quote($replace),
                            ))]);
                    }
                    $total += $count;
                    $this->line(sprintf('  %s.%s: %d fila(s)%s', $name, $column['name'], $count, $dry ? ' (sin cambios: dry-run)' : ''));
                }
            }
        }

        $this->info($total === 0
            ? "Nada que reescribir: no hay «{$from}» en la base de datos."
            : ($dry ? "{$total} fila(s) llevarían «{$to}» en lugar de «{$from}»." : "{$total} fila(s) reescritas: «{$from}» → «{$to}»."));

        return self::SUCCESS;
    }

    protected function wrap(string $column): string
    {
        return DB::getQueryGrammar()->wrap($column);
    }
}
