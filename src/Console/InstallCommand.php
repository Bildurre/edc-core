<?php

namespace Edc\Core\Console;

use Edc\Core\Auth\MotorAuth;
use Illuminate\Console\Command;

/**
 * Prepara el motor en una instalación nueva: crea los roles base y los
 * permisos con su reparto (doc 05). Idempotente: se puede ejecutar varias
 * veces sin duplicar.
 */
class InstallCommand extends Command
{
    protected $signature = 'motor:install';

    protected $description = 'Prepara el motor: roles base (admin, editor, user) y permisos.';

    public function handle(): int
    {
        // MotorAuth limpia también la caché de Spatie (puede sobrevivir a un
        // migrate:fresh, p. ej. con CACHE_STORE=file, y romper los chequeos).
        MotorAuth::syncRolesAndPermissions();

        foreach (config('motor.auth.roles', []) as $role) {
            $permissions = implode(', ', config("motor.auth.role_permissions.{$role}", [])) ?: '—';
            $this->line("  Rol asegurado: {$role} ({$permissions})");
        }

        $this->info('Motor instalado.');

        return self::SUCCESS;
    }
}
