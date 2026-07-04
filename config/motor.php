<?php

use Bgm\Core\Motor;

return [
    // Versión expuesta por la API (debe casar con Bgm\Core\Motor::VERSION).
    'version' => Motor::VERSION,

    // Locales de contenido del motor (DC-23). Cada juego puede ajustarlos.
    'default_locale' => 'es',
    'locales' => [
        'es' => ['name' => 'Español'],
        'eu' => ['name' => 'Euskara'],
        'en' => ['name' => 'English'],
    ],

    // Almacenamiento (DC-22): disco por defecto; S3/Spaces opcional por juego.
    'storage' => [
        'disk' => env('MOTOR_DISK', 'public'),
    ],

    // URLs de los frontends del juego (las SPA viven en otros orígenes).
    'frontend' => [
        // Web pública ('app'): destino de redirecciones (p. ej. verificar email).
        'app_url' => env('MOTOR_APP_URL', 'http://localhost:5173'),
        // Ruta (dentro de la app) a la que se llega tras verificar el email.
        'verified_path' => env('MOTOR_VERIFIED_PATH', '/cuenta?verified=1'),
    ],

    // Render de componentes a PNG (doc 01, DC-04, DC-05).
    'previews' => [
        // Apagado global (p. ej. en tests o entornos sin Chromium): las
        // entidades no encolan regeneraciones al crearse/editarse.
        'enabled' => env('MOTOR_PREVIEWS', true),
        // Disco y carpeta donde se guardan los PNG generados.
        'disk' => env('MOTOR_PREVIEWS_DISK', env('MOTOR_DISK', 'public')),
        'path' => 'previews',
        // deviceScaleFactor de la captura (calidad vs peso; afecta al PDF).
        'scale' => env('MOTOR_PREVIEWS_SCALE', 2),
        // Segundos máximos por captura.
        'timeout' => env('MOTOR_PREVIEWS_TIMEOUT', 60),
        // Vida (segundos) del token de servicio que autoriza a la ruta
        // /_render a pedir los datos por la API (DC-04).
        'token_ttl' => 300,
        // Base de la SPA que sirve la ruta /_render (por defecto, la app).
        'render_url' => env('MOTOR_RENDER_URL'),
        // Binarios: si no se indican, Browsershot usa el Chromium de puppeteer
        // y el node del PATH. En el droplet se fija chrome_path (DC-22).
        'chrome_path' => env('MOTOR_CHROME_PATH'),
        'node_binary' => env('MOTOR_NODE_BINARY'),
    ],

    // Generación de PDF (doc 02, DC-06, DC-07).
    'pdf' => [
        // Disco y carpeta donde se guardan los PDF generados.
        'disk' => env('MOTOR_PDF_DISK', env('MOTOR_DISK', 'public')),
        'path' => 'pdfs',
        // Horas de vida de los PDF temporales (colecciones a la carta).
        'temporary_ttl' => env('MOTOR_PDF_TTL', 24),
        // Layout por defecto si el export no declara uno.
        'default_layout' => 'card',
        // Presets de impresión (DC-07). Cada juego puede añadir o ajustar
        // (medidas en mm) publicando esta config o con Pdfs::layout() en el
        // boot de su AppServiceProvider. Columnas/filas se calculan del papel.
        'layouts' => [
            // Carta tamaño Magic (63x88) con marcas de corte: 9 por A4.
            // Cada juego ajusta el tamaño de SUS cartas aquí (o añade layouts).
            'card' => [
                'paper' => 'a4',
                'orientation' => 'portrait',
                'item_width' => 63,
                'item_height' => 88,
                'margin' => 6,
                'gap' => 4,
                'crop_marks' => true,
                'crop_mark_length' => 3,
            ],
            // Counters 25x25: rejilla densa.
            'counter' => [
                'paper' => 'a4',
                'orientation' => 'portrait',
                'item_width' => 25,
                'item_height' => 25,
                'margin' => 10,
                'gap' => 4,
                'crop_marks' => true,
                'crop_mark_length' => 2,
            ],
        ],
    ],

    // CRM de páginas y bloques (doc 03).
    'content' => [
        // Segundos de caché del payload público por (página, locale) — DC-10:
        // los cambios de página/bloques la invalidan al momento; el TTL corto
        // cubre los cambios en entidades que consultan los bloques-con-datos.
        'cache_ttl' => env('MOTOR_CONTENT_CACHE_TTL', 300),
        // Plantillas de página disponibles (clave => etiqueta). Cada juego
        // añade las suyas; la SPA decide qué hace con cada clave.
        'templates' => [
            'default' => 'Por defecto',
        ],
    ],

    // Configuración de la web pública (doc 10): catálogo de fuentes que
    // ofrece la página de Configuración del admin (clave => pila CSS). Son
    // pilas del sistema (sin peticiones externas); un juego puede añadir una
    // webfont propia registrando aquí su clave y cargando la fuente en su SPA.
    'site' => [
        'fonts' => [
            'system' => "system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
            'serif' => "Georgia, 'Iowan Old Style', 'Times New Roman', serif",
            'humanist' => "Verdana, 'Segoe UI', Tahoma, sans-serif",
            'rounded' => "ui-rounded, 'Hiragino Maru Gothic ProN', Quicksand, Comfortaa, system-ui, sans-serif",
            'mono' => 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
        ],
    ],

    // Copias de seguridad de la BBDD (doc 06, DC-16): spatie/laravel-backup
    // con la config derivada de aquí (ver Bgm\Core\Backup\MotorBackup).
    'backup' => [
        // Disco donde se guardan los zips. Si el juego no lo define en
        // filesystems, el motor crea uno local en storage/app/backups.
        'disk' => env('MOTOR_BACKUP_DISK', 'backups'),
        // Retención: días que se conservan todas las copias (backup:clean).
        'keep_days' => env('MOTOR_BACKUP_KEEP_DAYS', 14),
        // Incluir la carpeta de media (storage/app/public) en el zip.
        'include_media' => env('MOTOR_BACKUP_MEDIA', true),
    ],

    // Auth (DC-13, DC-14).
    'auth' => [
        // 'open' = registro público con rol user; 'invite' = registro deshabilitado.
        'registration' => env('MOTOR_REGISTRATION', 'open'),
        // Roles base del motor.
        'roles' => ['admin', 'editor', 'user'],
        // Roles con acceso al panel de administración.
        'admin_roles' => ['admin', 'editor'],
        // Permisos del motor: separan qué ve cada rol DENTRO del admin. Las
        // rutas se protegen con el middleware can: (los permisos de Spatie
        // entran por el Gate). El juego puede añadir permisos o redefinir el
        // reparto.
        'permissions' => ['manage-game', 'manage-web', 'manage-users'],
        'role_permissions' => [
            'admin' => ['manage-game', 'manage-web', 'manage-users'],
            // Los editores solo gestionan el JUEGO (modelos, iconos, PNG,
            // PDF); ni la web (páginas/configuración) ni los usuarios.
            'editor' => ['manage-game'],
        ],
    ],
];
