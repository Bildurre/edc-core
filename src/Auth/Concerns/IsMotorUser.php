<?php

namespace Bgm\Core\Auth\Concerns;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Trait que aporta a la entidad User del juego las capacidades del motor:
 * tokens de API (Sanctum) y roles/permisos (Spatie), más helpers de rol.
 */
trait IsMotorUser
{
    use HasApiTokens;
    use HasRoles;

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
