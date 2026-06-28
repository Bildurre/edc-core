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

        $router->aliasMiddleware('motor.admin', EnsureCanAccessAdmin::class);

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);

            $this->publishes([
                __DIR__ . '/../config/motor.php' => config_path('motor.php'),
            ], 'motor-config');
        }
    }
}
