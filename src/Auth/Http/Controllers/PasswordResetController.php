<?php

namespace Edc\Core\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Recuperación de contraseña (doc 05): el broker estándar de Laravel. El
 * enlace del correo apunta a la SPA pública (motor.frontend.app_url +
 * reset_path, ver MotorServiceProvider) con token y email en la query.
 */
class PasswordResetController extends Controller
{
    /** Envía el enlace de recuperación. Respuesta genérica: no revela si el email existe. */
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => __('motor::motor.password_reset_sent')]);
    }

    /** Restablece la contraseña con el token del correo. */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            },
        );

        abort_unless($status === Password::PasswordReset, 422, __($status));

        return response()->json(['message' => __('motor::motor.password_reset_done')]);
    }
}
