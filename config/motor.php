<?php

return [
    // Versión expuesta por la API (debe casar con Bgm\Core\Motor::VERSION).
    'version' => \Bgm\Core\Motor::VERSION,

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
