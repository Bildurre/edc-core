<?php

use Bgm\Core\Auth\Http\Controllers\AccountController;
use Bgm\Core\Auth\Http\Controllers\AuthController;
use Bgm\Core\Auth\Http\Controllers\EmailVerificationController;
use Bgm\Core\Auth\Http\Controllers\UserController;
use Bgm\Core\Backup\Http\Controllers\BackupController;
use Bgm\Core\Content\Http\Controllers\BlockController;
use Bgm\Core\Content\Http\Controllers\BlockTypeController;
use Bgm\Core\Content\Http\Controllers\ContentUploadController;
use Bgm\Core\Content\Http\Controllers\PageController;
use Bgm\Core\Content\Http\Controllers\PublicPageController;
use Bgm\Core\Content\Http\Controllers\SitemapController;
use Bgm\Core\Icons\Http\Controllers\IconController;
use Bgm\Core\Pdf\Http\Controllers\PdfCollectionController;
use Bgm\Core\Pdf\Http\Controllers\PdfController;
use Bgm\Core\Previews\Http\Controllers\PreviewController;
use Bgm\Core\Previews\Http\Controllers\RenderDataController;
use Bgm\Core\Site\Http\Controllers\SiteSettingsController;
use Illuminate\Support\Facades\Route;

/*
| Rutas del motor. Se cargan desde MotorServiceProvider::boot().
*/

Route::get('api/motor/ping', function () {
    return response()->json([
        'name' => 'Boardgame Motor',
        'package' => 'bgm/core',
        'version' => config('motor.version'),
        'default_locale' => config('motor.default_locale'),
        'locales' => array_keys(config('motor.locales', [])),
    ]);
});

// Sitemap de la web pública (doc 10, DC-18): páginas del CRM + entidades
// registradas por el juego (facade Sitemap). Vive fuera de /api porque los
// buscadores lo esperan en la raíz del dominio de la API o proxied.
Route::get('sitemap.xml', SitemapController::class);

// Locales de contenido disponibles (para los selectores del front).
Route::get('api/locales', function () {
    return response()->json([
        'default' => config('motor.default_locale'),
        'locales' => collect(config('motor.locales', []))
            ->map(fn ($meta, $code) => ['code' => $code, 'name' => $meta['name'] ?? $code])
            ->values(),
    ]);
});

// Grupo 'api' para heredar lo que la app configura (incl. SetLocale del motor,
// añadido en bootstrap/app.php). Sin él, estas rutas no localizan las respuestas.
Route::prefix('api')->middleware('api')->group(function () {
    // --- Público ---
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Verificación de email (DC-14): enlace firmado que llega por correo. El
    // nombre 'verification.verify' es el que espera la notificación de Laravel.
    Route::get('auth/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Biblioteca de iconos (para el selector del editor WYSIWYG).
    Route::get('icons', [IconController::class, 'index']);

    // Datos para la ruta /_render de la SPA: exige token de servicio (DC-04).
    Route::get('render/{entity}/{id}', [RenderDataController::class, 'show'])
        ->whereNumber('id');

    // Descarga de PDF: permanentes públicos (expositor); temporales, solo el
    // dueño o un admin (lo comprueba el controlador).
    Route::get('pdfs/{pdf}/download', [PdfController::class, 'download']);

    // Configuración de la web (doc 10): la SPA la aplica al arrancar
    // (título, favicon, fuentes, acento fijo o aleatorio…). Los ficheros de
    // fuente se sirven por aquí para heredar el CORS del grupo api.
    Route::get('site', [SiteSettingsController::class, 'show']);
    Route::get('site/fonts/{path}', [SiteSettingsController::class, 'font'])
        ->where('path', '[A-Za-z0-9/._\-]+');

    // Render público del CRM (doc 03): navegación, home y página por slug
    // traducible (resuelto en cualquier locale; la SPA redirige a la canónica).
    Route::get('pages/nav', [PublicPageController::class, 'nav']);
    Route::get('pages/home', [PublicPageController::class, 'home']);
    Route::get('pages/{slug}', [PublicPageController::class, 'show'])
        ->where('slug', '[a-z0-9\-]+');

    // --- Autenticado (token Sanctum) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/email/verification-notification', [
            EmailVerificationController::class, 'resend',
        ])->middleware('throttle:6,1');

        // Panel de usuario (base; cada juego lo amplía).
        Route::get('account', [AccountController::class, 'show']);
        Route::put('account', [AccountController::class, 'update']);
        Route::put('account/password', [AccountController::class, 'updatePassword']);

        // Colección temporal "para imprimir" (doc 02).
        Route::prefix('pdf-collection')->group(function () {
            Route::get('/', [PdfCollectionController::class, 'index']);
            Route::post('items', [PdfCollectionController::class, 'store']);
            Route::delete('items/{item}', [PdfCollectionController::class, 'destroy'])->whereNumber('item');
            Route::delete('/', [PdfCollectionController::class, 'clear']);
            Route::post('generate', [PdfCollectionController::class, 'generate']);
            Route::get('pdfs/{pdf}', [PdfCollectionController::class, 'show'])->whereNumber('pdf');
        });

        // --- Solo admin/editor ---
        Route::middleware('motor.admin')->group(function () {
            Route::get('admin/ping', function () {
                return response()->json(['ok' => true, 'area' => 'admin']);
            });

            // Gestión de la biblioteca de iconos (assets del juego).
            Route::middleware('can:manage-game')->group(function () {
                Route::post('admin/icons', [IconController::class, 'store']);
                Route::delete('admin/icons/{icon}', [IconController::class, 'destroy']);
            });

            // Gestión de usuarios (doc 05): solo manage-users.
            Route::prefix('admin/users')->middleware('can:manage-users')->group(function () {
                Route::get('/', [UserController::class, 'index']);
                Route::post('/', [UserController::class, 'store']);
                Route::put('{id}', [UserController::class, 'update'])->whereNumber('id');
                Route::post('{id}/toggle-verified', [UserController::class, 'toggleVerified'])->whereNumber('id');
                Route::delete('{id}', [UserController::class, 'destroy'])->whereNumber('id');
            });

            // Configuración de la web (doc 10) y CRM (doc 03): son "la web",
            // solo manage-web (los editores no entran).
            Route::middleware('can:manage-web')->group(function () {
                Route::get('admin/settings/site', [SiteSettingsController::class, 'edit']);
                Route::put('admin/settings/site', [SiteSettingsController::class, 'update']);
                Route::post('admin/settings/fonts', [SiteSettingsController::class, 'storeFont']);
            });

            // Copias de seguridad (doc 06): listar, crear, descargar, borrar.
            Route::prefix('admin/backups')->middleware('can:manage-web')->group(function () {
                Route::get('/', [BackupController::class, 'index']);
                Route::post('/', [BackupController::class, 'store']);
                Route::get('{file}/download', [BackupController::class, 'download'])
                    ->where('file', '[A-Za-z0-9._\-]+');
                Route::delete('{file}', [BackupController::class, 'destroy'])
                    ->where('file', '[A-Za-z0-9._\-]+');
            });

            // CRM de páginas y bloques (doc 03). Las estáticas antes que {page}.
            Route::get('admin/block-types', [BlockTypeController::class, 'index'])->middleware('can:manage-web');
            Route::post('admin/content/uploads', [ContentUploadController::class, 'store'])->middleware('can:manage-web');
            Route::prefix('admin/pages')->middleware('can:manage-web')->group(function () {
                Route::get('/', [PageController::class, 'index']);
                Route::post('/', [PageController::class, 'store']);
                Route::get('templates', [PageController::class, 'templates']);
                Route::post('reorder', [PageController::class, 'reorder']);
                Route::post('{id}/restore', [PageController::class, 'restore'])->whereNumber('id');
                Route::delete('{id}/force', [PageController::class, 'forceDestroy'])->whereNumber('id');
                Route::post('{page}/set-home', [PageController::class, 'setHome'])->whereNumber('page');
                Route::get('{page}', [PageController::class, 'show'])->whereNumber('page');
                Route::put('{page}', [PageController::class, 'update'])->whereNumber('page');
                Route::delete('{page}', [PageController::class, 'destroy'])->whereNumber('page');
                // Bloques de una página.
                Route::get('{page}/blocks', [BlockController::class, 'index'])->whereNumber('page');
                Route::post('{page}/blocks', [BlockController::class, 'store'])->whereNumber('page');
                Route::post('{page}/blocks/reorder', [BlockController::class, 'reorder'])->whereNumber('page');
            });
            Route::middleware('can:manage-web')->group(function () {
                Route::put('admin/blocks/{block}', [BlockController::class, 'update'])->whereNumber('block');
                Route::delete('admin/blocks/{block}', [BlockController::class, 'destroy'])->whereNumber('block');
            });

            // Gestor de previews PNG (estado, lotes por tipo, individuales,
            // limpieza de huérfanos). Las rutas estáticas van antes que {id}.
            Route::prefix('admin/previews')->middleware('can:manage-game')->group(function () {
                Route::get('/', [PreviewController::class, 'index']);
                Route::post('clean', [PreviewController::class, 'clean']);
                Route::get('{entity}/items', [PreviewController::class, 'items']);
                Route::post('{entity}/generate', [PreviewController::class, 'generateType']);
                Route::post('{entity}/regenerate', [PreviewController::class, 'regenerateType']);
                Route::delete('{entity}', [PreviewController::class, 'destroyType']);
                Route::get('{entity}/{id}', [PreviewController::class, 'show'])->whereNumber('id');
                Route::post('{entity}/{id}/regenerate', [PreviewController::class, 'regenerate'])
                    ->whereNumber('id');
                Route::delete('{entity}/{id}', [PreviewController::class, 'destroy'])->whereNumber('id');
            });

            // Gestor de PDF (doc 02): listar por export/entidad, generar,
            // regenerar y borrar con un clic.
            Route::prefix('admin/pdfs')->middleware('can:manage-game')->group(function () {
                Route::get('exports', [PdfController::class, 'exports']);
                Route::get('/', [PdfController::class, 'index']);
                Route::post('generate', [PdfController::class, 'generate']);
                Route::post('{pdf}/regenerate', [PdfController::class, 'regenerate'])->whereNumber('pdf');
                Route::delete('{pdf}', [PdfController::class, 'destroy'])->whereNumber('pdf');
            });
        });
    });
});
