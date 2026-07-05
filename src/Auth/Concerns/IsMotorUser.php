<?php

namespace Bgm\Core\Auth\Concerns;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Trait que aporta a la entidad User del juego las capacidades del motor:
 * tokens de API (Sanctum) y roles/permisos (Spatie), más helpers de rol y
 * el idioma preferido para sus correos. El User del juego debe implementar
 * también Illuminate\Contracts\Translation\HasLocalePreference para que las
 * notificaciones (verificación, reset…) salgan en su idioma.
 */
trait IsMotorUser
{
    use HasApiTokens;
    use HasRoles;

    /**
     * Idioma de los correos del usuario: el guardado al registrarse o en su
     * último login (columna `locale`), con fallback al de la app.
     */
    public function preferredLocale(): string
    {
        return $this->locale ?? config('app.locale');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isEditor(): bool
    {
        return $this->hasRole('editor');
    }

    public function canAccessAdmin(): bool
    {
        return $this->hasAnyRole(config('motor.auth.admin_roles', ['admin', 'editor']));
    }
}
