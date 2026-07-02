<?php

namespace Bgm\Core\Auth\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Verificación de email (DC-14). El enlace del correo apunta a la API (ruta
 * firmada 'verification.verify'); al verificar se redirige a la web pública,
 * así el flujo funciona sin sesión ni token en el navegador.
 */
class EmailVerificationController extends Controller
{
    /** Modelo User del juego (configurable en config/auth.php). */
    protected function userModel(): string
    {
        return config('auth.providers.users.model', User::class);
    }

    /** GET firmado desde el correo: marca el email como verificado. */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $model = $this->userModel();
        $user = $model::findOrFail($id);

        // El hash del enlace debe corresponder al email actual del usuario.
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->away(
            config('motor.frontend.app_url').config('motor.frontend.verified_path')
        );
    }

    /** Reenvía el correo de verificación al usuario autenticado. */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof MustVerifyEmail) {
            abort(400, __('motor::motor.verification_not_supported'));
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('motor::motor.already_verified')]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => __('motor::motor.verification_sent')]);
    }
}
