<?php

namespace Edc\Core\Backup;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

/**
 * Restauración de una copia de spatie/laravel-backup (doc 06): el zip trae
 * la BBDD como dump SQL (carpeta db-dumps/, conexiones mysql/pgsql…) o como
 * fichero .sqlite tal cual (así la incluye MotorBackup cuando el juego usa
 * SQLite). Restaurar = importar esa BBDD MACHACANDO la actual.
 *
 * Límites (documentados también en el panel del admin):
 * - SOLO la base de datos: los archivos de storage que pueda traer el zip
 *   (media, fuentes…) no se restauran.
 * - Dumps SQL: se cargan enteros en memoria y se ejecutan con unprepared();
 *   para BBDD enormes, mejor restaurar por consola. Dumps comprimidos
 *   (.sql.gz) no soportados.
 * - La restauración puede invalidar los tokens de sesión vigentes (también
 *   el del propio admin): toca volver a entrar.
 */
class BackupRestorer
{
    /** ¿El zip contiene una BBDD restaurable (dump SQL o fichero SQLite)? */
    public function containsDatabase(string $zipPath): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return false;
        }

        [$dumps, $sqlites] = $this->scan($zip);
        $zip->close();

        return $dumps !== [] || $sqlites !== [];
    }

    /**
     * Restaura la BBDD del zip sobre la conexión por defecto y devuelve la
     * entrada restaurada. Aborta con 422 si el zip no trae ninguna BBDD.
     */
    public function restore(string $zipPath): string
    {
        $zip = new ZipArchive;
        abort_unless(
            $zip->open($zipPath, ZipArchive::RDONLY) === true,
            422,
            __('motor::motor.backup_restore_no_database'),
        );

        try {
            [$dumps, $sqlites] = $this->scan($zip);

            $connection = config('database.default');
            $driver = config("database.connections.{$connection}.driver");
            $databaseFile = $driver === 'sqlite'
                ? config("database.connections.{$connection}.database")
                : null;

            // SQLite en fichero: el zip trae la BBDD tal cual (MotorBackup);
            // sustituir el fichero es la restauración completa.
            if ($sqlites !== [] && is_string($databaseFile) && $databaseFile !== ':memory:') {
                return $this->finish($this->replaceSqliteFile($zip, $sqlites, $connection, $databaseFile));
            }

            // Resto de drivers (o zips de otra instalación): importar el dump.
            if ($dumps !== []) {
                return $this->finish($this->importDump($zip, $dumps, $connection, $driver));
            }

            abort(422, __('motor::motor.backup_restore_no_database'));
        } finally {
            $zip->close();
        }
    }

    /** Entradas de BBDD del zip: [dumps SQL de db-dumps/, ficheros .sqlite]. */
    protected function scan(ZipArchive $zip): array
    {
        $dumps = [];
        $sqlites = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_contains($name, 'db-dumps/') && str_ends_with($name, '.sql')) {
                $dumps[] = $name;
            } elseif (str_ends_with($name, '.sqlite')) {
                $sqlites[] = $name;
            }
        }

        return [$dumps, $sqlites];
    }

    /** Sustituye el fichero SQLite actual por el del zip. */
    protected function replaceSqliteFile(ZipArchive $zip, array $sqlites, string $connection, string $databaseFile): string
    {
        // La entrada cuyo nombre casa con la BBDD actual; si no (copia de
        // otra instalación con otro nombre), la primera .sqlite del zip.
        $entry = collect($sqlites)
            ->first(fn (string $name) => basename($name) === basename($databaseFile), $sqlites[0]);

        // Suelta la conexión abierta antes de pisar el fichero. En tests NO:
        // la suite corre sobre el :memory: compartido que gestiona
        // RefreshDatabase y purgarlo la corrompería; ahí la copia de bytes
        // tampoco lo necesita (misma razón de ser que el guard de
        // HasPreviewImage::regeneratePreviews()).
        if (! app()->runningUnitTests()) {
            DB::purge($connection);
        }

        $source = $zip->getStream($entry);
        abort_unless($source !== false, 422, __('motor::motor.backup_restore_no_database'));

        $target = fopen($databaseFile, 'wb');
        stream_copy_to_stream($source, $target);
        fclose($target);
        fclose($source);

        return $entry;
    }

    /** Vacía el esquema actual e importa el dump SQL del zip. */
    protected function importDump(ZipArchive $zip, array $dumps, string $connection, string $driver): string
    {
        // El dump que casa con el driver actual (spatie los nombra
        // "{driver}-{bbdd}.sql"); si no, el primero del zip.
        $prefixes = match ($driver) {
            'mysql', 'mariadb' => ['mysql-', 'mariadb-'],
            'pgsql' => ['postgresql-', 'pgsql-'],
            'sqlite' => ['sqlite-'],
            default => [],
        };
        $entry = collect($dumps)->first(
            fn (string $name) => collect($prefixes)->contains(
                fn (string $prefix) => str_starts_with(basename($name), $prefix),
            ),
            $dumps[0],
        );

        $sql = $zip->getFromName($entry);
        abort_unless(is_string($sql) && trim($sql) !== '', 422, __('motor::motor.backup_restore_no_database'));

        // Borrón y cuenta nueva: los dumps de pg_dump no traen DROPs y sobre
        // un esquema vivo chocarían. Tabla a tabla (sin dropAllTables): el
        // VACUUM de SQLite no puede correr dentro de una transacción.
        $schema = Schema::connection($connection);
        $schema->disableForeignKeyConstraints();

        try {
            foreach (array_column($schema->getTables(), 'name') as $table) {
                $schema->dropIfExists($table);
            }
        } finally {
            $schema->enableForeignKeyConstraints();
        }

        DB::connection($connection)->unprepared($sql);

        return $entry;
    }

    /** Tras restaurar: fuera cachés (settings, contenido, permisos rancios). */
    protected function finish(string $entry): string
    {
        Cache::flush();

        return $entry;
    }
}
