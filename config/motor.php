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

    // Auth (DC-13, DC-14).
    'auth' => [
        // 'open' = registro público con rol user; 'invite' = registro deshabilitado.
        'registration' => env('MOTOR_REGISTRATION', 'open'),
        // Roles base del motor.
        'roles' => ['admin', 'editor', 'user'],
        // Roles con acceso al panel de administración.
        'admin_roles' => ['admin', 'editor'],
    ],
];
