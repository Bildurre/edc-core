<?php

use Illuminate\Support\Facades\Route;

/*
| Rutas del motor. Se cargan desde MotorServiceProvider::boot().
| En Fase 0 solo exponemos un "ping" que demuestra que el juego consume
| el paquete bgm/core. Las rutas reales (auth, pages, blocks, pdf…) llegan
| en sus fases.
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
