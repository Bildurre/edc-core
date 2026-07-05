<?php

namespace Bgm\Core\Backup;

use Bgm\Core\Site\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Configuración de la copia automática (doc 06), editable desde el admin:
 * activada, frecuencia (diaria/semanal), hora, día y retención. Se guarda
 * como un JSON bajo la clave 'backup' (misma tabla settings que la
 * configuración de la web) y la lee el scheduler del motor
 * (MotorBackup::schedule) en cada schedule:run.
 */
class BackupSettings
{
    protected const CACHE_KEY = 'motor.settings.backup';

    /** Valores por defecto (la retención base sale de motor.backup). */
    public function defaults(): array
    {
        return [
            'auto' => true,
            'frequency' => 'daily', // daily | weekly
            'time' => '03:00',
            'weekday' => 1,         // 1 = lunes … 7 = domingo (solo semanal)
            'keep_days' => (int) config('motor.backup.keep_days', 14),
        ];
    }

    /**
     * Ajustes efectivos (guardados sobre los defaults), cacheados. Con
     * rescue: se lee en el boot del motor y la BBDD puede no existir aún
     * (instalación, migraciones).
     */
    public function get(): array
    {
        return rescue(function () {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                $saved = Setting::query()->where('key', 'backup')->value('value') ?? [];

                return [...$this->defaults(), ...$saved];
            });
        }, $this->defaults(), report: false);
    }

    /** Guarda (mezclando sobre lo actual) e invalida la caché. */
    public function update(array $data): array
    {
        $value = [...$this->get(), ...$data];

        Setting::query()->updateOrCreate(['key' => 'backup'], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);

        return $this->get();
    }
}
