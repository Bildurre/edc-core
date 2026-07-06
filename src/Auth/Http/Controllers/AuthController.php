<?php

namespace Edc\Core\Auth\Http\Controllers;

use App\Models\User;
use Edc\Core\Auth\Http\Resources\UserResource;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Modelo User del juego (configurable en config/auth.php). */
    protected function userModel(): string
    {
        return config('auth.providers.users.model', User::class);
    }

    public function register(Request $request): JsonResponse
    {
        if (config('motor.auth.registration') !== 'open') {
            abort(403, __('motor::motor.registration_disabled'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Protección de datos: el registro exige aceptación explícita.
            'privacy' => ['accepted'],
        ]);

        $model = $this->userModel();
        $user = $model::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->assignRole('user');

        // El idioma con el que se registró: sus correos (verificación,
        // reset…) saldrán en ese locale (preferredLocale). Con rescue: si la
        // instalación aún no migró la columna, el registro NO se rompe (el
        // detalle queda en los logs, nunca en la respuesta).
        rescue(fn () => $user->forceFill(['locale' => app()->getLocale()])->save());

        // Si el User del juego implementa MustVerifyEmail, Laravel envía el
        // correo de verificación al escuchar este evento (DC-14).
        event(new Registered($user));

        $token = $user->createToken('edc')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $model = $this->userModel();
        $user = $model::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('motor::motor.invalid_credentials')],
            ]);
        }

        // El idioma preferido sigue al último login: los correos posteriores
        // (reset de contraseña…) salen en el idioma en que usa la web. Con
        // rescue: una migración pendiente jamás debe tumbar el login (el
        // detalle va a los logs, no al frontend).
        if ($user->locale !== app()->getLocale()) {
            rescue(fn () => $user->forceFill(['locale' => app()->getLocale()])->save());
        }

        $token = $user->createToken('edc')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('motor::motor.logged_out')]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles'));
    }

    /**
     * Traspaso de sesión entre las SPA (web <-> admin, orígenes distintos):
     * el origen pide un código de UN SOLO USO (60 s) y lo añade al enlace;
     * el destino lo canjea por un token PROPIO al cargar. El token Sanctum
     * nunca viaja en la URL.
     */
    public function handoff(Request $request): JsonResponse
    {
        $code = Str::random(48);
        Cache::put("motor.handoff.{$code}", $request->user()->getKey(), now()->addSeconds(60));

        return response()->json(['code' => $code]);
    }

    /** Canjea el código (Cache::pull: un solo uso) por un token nuevo. */
    public function consumeHandoff(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        $userId = Cache::pull("motor.handoff.{$data['code']}");
        abort_unless($userId !== null, 401);

        $model = $this->userModel();
        $user = $model::query()->findOrFail($userId);
        $token = $user->createToken('edc')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
        ]);
    }
}
