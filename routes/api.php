<?php

use Bgm\Core\Auth\Http\Controllers\AccountController;
use Bgm\Core\Auth\Http\Controllers\AuthController;
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

Route::prefix('api')->group(function () {
    // --- Público ---
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // --- Autenticado (token Sanctum) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // Panel de usuario (base; cada juego lo amplía).
        Route::get('account', [AccountController::class, 'show']);
        Route::put('account', [AccountController::class, 'update']);
        Route::put('account/password', [AccountController::class, 'updatePassword']);

        // --- Solo admin/editor ---
        Route::middleware('motor.admin')->group(function () {
            Route::get('admin/ping', function () {
                return response()->json(['ok' => true, 'area' => 'admin']);
            });
        });
    });
});
