<?php

namespace Bgm\Core;

use Bgm\Core\Auth\Http\Middleware\EnsureCanAccessAdmin;
use Bgm\Core\Backup\MotorBackup;
use Bgm\Core\Console\InstallCommand;
use Bgm\Core\Console\PdfCleanupCommand;
use Bgm\Core\Console\PreviewManageCommand;
use Bgm\Core\Content\BlockTypeRegistry;
use Bgm\Core\Content\BlockTypes\CtaBlock;
use Bgm\Core\Content\BlockTypes\HeaderBlock;
use Bgm\Core\Content\BlockTypes\IndexBlock;
use Bgm\Core\Content\BlockTypes\QuoteBlock;
use Bgm\Core\Content\BlockTypes\TextBlock;
use Bgm\Core\Content\BlockTypes\TextCardBlock;
use Bgm\Core\Content\PagePdfExport;
use Bgm\Core\Pdf\PdfExportRegistry;
use Bgm\Core\Previews\PreviewRegistry;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class MotorServiceProvider extends ServiceProvider
{
    /**
     * Registro de servicios del motor.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/motor.php', 'motor');

        // Registro de entidades renderizables (doc 01): único por app; cada
        // juego registra las suyas vía la facade Previews en su provider.
        $this->app->singleton(PreviewRegistry::class);

        // Registro de exports de PDF (doc 02): facade Pdfs.
        $this->app->singleton(PdfExportRegistry::class);

        // Registro de tipos de bloque del CRM (doc 03): facade Blocks. El
        // motor aporta los de presentación; cada juego añade los suyos.
        $this->app->singleton(BlockTypeRegistry::class, function () {
            $registry = new BlockTypeRegistry;
            foreach ([HeaderBlock::class, TextBlock::class, TextCardBlock::class, QuoteBlock::class, CtaBlock::class, IndexBlock::class] as $type) {
                $registry->register($type);
            }

            return $registry;
        });
    }

    /**
     * Arranque del motor: rutas, middleware, comandos, config publicable.
     */
    public function boot(Router $router): void
    {
        // PDF de páginas imprimibles del CRM (doc 03 + doc 02).
        $this->app->make(PdfExportRegistry::class)->register('pages', PagePdfExport::class);

        // Copias de seguridad (doc 06): config de spatie derivada de motor.backup.
        MotorBackup::applyConfig();

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'motor');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'motor');

        $router->aliasMiddleware('motor.admin', EnsureCanAccessAdmin::class);
        // El SetLocale del motor lo registra cada juego en su bootstrap/app.php
        // (appendToGroup 'api'), porque la config de middleware de la app es la
        // autoridad y sobrescribe los grupos en Laravel 12.

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, PreviewManageCommand::class, PdfCleanupCommand::class]);

            $this->publishes([
                __DIR__.'/../config/motor.php' => config_path('motor.php'),
            ], 'motor-config');

            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/motor'),
            ], 'motor-lang');
        }
    }
}
