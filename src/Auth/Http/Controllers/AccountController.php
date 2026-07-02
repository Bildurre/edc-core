<?php

namespace Bgm\Core\Auth\Http\Controllers;

use Bgm\Core\Auth\Http\Resources\UserResource;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Datos de cuenta del usuario autenticado. Base del panel de usuario; cada
 * juego puede ampliar con sus propios campos/secciones.
 */
class AccountController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles'));
    }

    public function update(Request $request): UserResource
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        // Cambiar el email invalida la verificación anterior (DC-14): se
        // vuelve a "sin verificar" y se envía un correo al email nuevo.
        $emailChanged = $data['email'] !== $user->email;
        if ($emailChanged && $user instanceof MustVerifyEmail) {
            $data['email_verified_at'] = null;
        }

        $user->forceFill($data)->save();

        if ($emailChanged && $user instanceof MustVerifyEmail) {
            $user->sendEmailVerificationNotification();
        }

        return new UserResource($user->load('roles'));
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('motor::motor.current_password_incorrect')],
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => __('motor::motor.password_updated')]);
    }
}
