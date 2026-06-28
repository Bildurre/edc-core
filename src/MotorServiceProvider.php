<?php

namespace Bgm\Core;

use Illuminate\Support\ServiceProvider;

class MotorServiceProvider extends ServiceProvider
{
    /**
     * Registro de servicios del motor.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/motor.php', 'motor');
    }

    /**
     * Arranque del motor: rutas, config publicable, migraciones (futuro).
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        $this->publishes([
            __DIR__ . '/../config/motor.php' => config_path('motor.php'),
        ], 'motor-config');
    }
}
