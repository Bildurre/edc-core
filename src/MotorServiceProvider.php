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
use Bgm\Core\Content\Models\Page;
use Bgm\Core\Content\PagePdfExport;
use Bgm\Core\Content\SitemapRegistry;
use Bgm\Core\Pdf\PdfExportRegistry;
use Bgm\Core\Previews\PreviewRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Console\Scheduling\Schedule;
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

        // Registro de URLs del sitemap (doc 10): facade Sitemap. El motor
        // aporta las páginas del CRM; cada juego añade sus entidades.
        $this->app->singleton(SitemapRegistry::class);

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

        // Copias de seguridad (doc 06): config de spatie derivada de motor.backup
        // y copia automática programada según lo configurado en el admin
        // (BackupSettings). El juego solo necesita el cron de schedule:run.
        MotorBackup::applyConfig();
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            MotorBackup::schedule($schedule);
        });

        // Sitemap (doc 10): las páginas publicadas del CRM, con la home en la
        // raíz de cada locale. Los juegos añaden sus entidades con Sitemap::add.
        $this->app->make(SitemapRegistry::class)->add(function () {
            return Page::query()->published()->get()
                ->map(fn (Page $page) => [
                    'slugs' => collect($page->getTranslations('slug'))
                        ->map(fn (string $slug) => $page->is_home ? '' : $slug)
                        ->all(),
                    'updated_at' => $page->updated_at?->toDateString(),
                ])
                ->all();
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'motor');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'motor');

        $router->aliasMiddleware('motor.admin', EnsureCanAccessAdmin::class);

        // Recuperación de contraseña (doc 05): el enlace del correo apunta a
        // la SPA pública, con token + email en la query.
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $base = rtrim(config('motor.frontend.app_url', config('app.url')), '/');
            $path = config('motor.frontend.reset_path', '/restablecer');

            return $base.$path.'?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset());
        });
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
