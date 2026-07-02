<?php

use Bgm\Core\Auth\Http\Controllers\AccountController;
use Bgm\Core\Auth\Http\Controllers\AuthController;
use Bgm\Core\Auth\Http\Controllers\EmailVerificationController;
use Bgm\Core\Icons\Http\Controllers\IconController;
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

        // --- Solo admin/editor ---
        Route::middleware('motor.admin')->group(function () {
            Route::get('admin/ping', function () {
                return response()->json(['ok' => true, 'area' => 'admin']);
            });

            // Gestión de la biblioteca de iconos.
            Route::post('admin/icons', [IconController::class, 'store']);
            Route::delete('admin/icons/{icon}', [IconController::class, 'destroy']);
        });
    });
});
