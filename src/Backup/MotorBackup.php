<?php

namespace Bgm\Core\Backup;

use Illuminate\Support\Str;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

/**
 * Copias de seguridad (doc 06, DC-16): el motor usa spatie/laravel-backup y
 * deriva su config de motor.backup para que el juego no tenga que publicar
 * config/backup.php (puede hacerlo si quiere afinar; lo puesto aquí solo
 * pisa las claves que gobierna el motor).
 */
class MotorBackup
{
    /** Aplica la config de spatie/laravel-backup a partir de motor.backup. */
    public static function applyConfig(): void
    {
        $disk = config('motor.backup.disk', 'backups');

        // Disco local por defecto si el juego no lo define en filesystems.
        if (! config("filesystems.disks.{$disk}")) {
            config(["filesystems.disks.{$disk}" => [
                'driver' => 'local',
                'root' => storage_path('app/backups'),
            ]]);
        }

        $include = [];
        $connection = config('database.default');
        $databases = [$connection];

        // SQLite: el fichero de la BBDD entra en el zip tal cual (el dump de
        // spatie exige el binario sqlite3, que no siempre está); para el
        // resto (mysql, pgsql…) se usa el dump normal.
        if (config("database.connections.{$connection}.driver") === 'sqlite') {
            $databases = [];
            $file = config("database.connections.{$connection}.database");
            if (is_string($file) && $file !== ':memory:' && is_file($file)) {
                $include[] = $file;
            }
        }

        if (config('motor.backup.include_media') && is_dir(storage_path('app/public'))) {
            $include[] = storage_path('app/public');
        }

        $name = Str::slug(config('app.name', 'motor'));

        config([
            'backup.backup.name' => $name,
            'backup.backup.source.files.include' => $include,
            'backup.backup.source.databases' => $databases,
            'backup.backup.destination.disks' => [$disk],
            'backup.cleanup.default_strategy.keep_all_backups_for_days' => (int) config('motor.backup.keep_days', 14),
            // Sin notificaciones por correo: el gestor del admin ya avisa.
            'backup.notifications.notifications' => [],
            // backup:list / backup:monitor miran este destino.
            'backup.monitor_backups' => [[
                'name' => $name,
                'disks' => [$disk],
                'health_checks' => [
                    MaximumAgeInDays::class => 1,
                    MaximumStorageInMegabytes::class => 5000,
                ],
            ]],
        ]);
    }
}
