<?php

namespace Bgm\Core;

use Bgm\Core\Auth\Http\Middleware\EnsureCanAccessAdmin;
use Bgm\Core\Console\InstallCommand;
use Illuminate\Routing\Router;
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
     * Arranque del motor: rutas, middleware, comandos, config publicable.
     */
    public function boot(Router $router): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $router->aliasMiddleware('motor.admin', EnsureCanAccessAdmin::class);
        // El SetLocale del motor lo registra cada juego en su bootstrap/app.php
        // (appendToGroup 'api'), porque la config de middleware de la app es la
        // autoridad y sobrescribe los grupos en Laravel 12.

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);

            $this->publishes([
                __DIR__ . '/../config/motor.php' => config_path('motor.php'),
            ], 'motor-config');
        }
    }
}
