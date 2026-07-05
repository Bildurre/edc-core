<?php

namespace Bgm\Core\Backup;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

/**
 * Copias de seguridad (doc 06, DC-16): el motor usa spatie/laravel-backup y
 * deriva su config de motor.backup para que el juego no tenga que publicar
 * config/backup.php (puede hacerlo si quiere afinar; lo puesto aquí solo
 * pisa las claves que gobierna el motor). La copia automática (frecuencia,
 * hora, retención) se edita desde el admin (BackupSettings) y la programa
 * schedule(): el juego solo necesita el cron de `schedule:run`.
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
        $settings = app(BackupSettings::class)->get();

        // Salud del monitor acorde a la copia automática configurada: con
        // frecuencia semanal, una copia de 3 días es normal; desactivada,
        // no alarma mientras haya alguna dentro de la retención.
        $maxAgeDays = ! ($settings['auto'] ?? true)
            ? (int) $settings['keep_days'] + 1
            : (($settings['frequency'] ?? 'daily') === 'weekly' ? 8 : 2);

        config([
            'backup.backup.name' => $name,
            'backup.backup.source.files.include' => $include,
            'backup.backup.source.databases' => $databases,
            'backup.backup.destination.disks' => [$disk],
            // Retención: la gobierna el admin (con motor.backup.keep_days de base).
            'backup.cleanup.default_strategy.keep_all_backups_for_days' => (int) $settings['keep_days'],
            // Sin notificaciones por correo: el gestor del admin ya avisa.
            'backup.notifications.notifications' => [],
            // backup:list / backup:monitor miran este destino.
            'backup.monitor_backups' => [[
                'name' => $name,
                'disks' => [$disk],
                'health_checks' => [
                    MaximumAgeInDays::class => $maxAgeDays,
                    MaximumStorageInMegabytes::class => 5000,
                ],
            ]],
        ]);
    }

    /**
     * Programa la copia automática según lo configurado en el admin. La lee
     * en cada schedule:run (los ajustes se aplican sin redeploy). Se ejecuta
     * en el MISMO proceso (call + Artisan::call) para que la retención de
     * applyConfig gobierne también el backup:clean.
     */
    public static function schedule(Schedule $schedule): void
    {
        $settings = app(BackupSettings::class)->get();

        if (! ($settings['auto'] ?? true)) {
            return;
        }

        $event = $schedule->call(function () {
            Artisan::call('backup:run', ['--disable-notifications' => true]);
            Artisan::call('backup:clean', ['--disable-notifications' => true]);
        })->name('motor:backup')->withoutOverlapping();

        if (($settings['frequency'] ?? 'daily') === 'weekly') {
            // BackupSettings usa 1=lunes … 7=domingo; weeklyOn espera 0-6.
            $event->weeklyOn(((int) $settings['weekday']) % 7, $settings['time']);
        } else {
            $event->dailyAt($settings['time']);
        }
    }
}
