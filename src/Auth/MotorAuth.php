<?php

namespace Bgm\Core\Auth;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles y permisos del motor (doc 05): asegura los roles, los permisos y su
 * reparto desde config (motor.auth.*). Lo usan el instalador, el seeder del
 * juego y los tests — una sola fuente de verdad.
 *
 * Permisos base: manage-game (entidades del juego, iconos, PNG, PDF),
 * manage-web (CRM de páginas y configuración) y manage-users. Los editores
 * llevan solo manage-game; el juego puede redefinir el reparto en config.
 */
class MotorAuth
{
    public static function syncRolesAndPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (config('motor.auth.permissions', []) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $assignments = config('motor.auth.role_permissions', []);

        foreach (config('motor.auth.roles', ['admin', 'editor', 'user']) as $name) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($assignments[$name] ?? []);
        }
    }
}
