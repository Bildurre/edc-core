<?php

namespace Bgm\Core\Console;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Prepara el motor en una instalación nueva: crea los roles base.
 * Idempotente: se puede ejecutar varias veces sin duplicar.
 */
class InstallCommand extends Command
{
    protected $signature = 'motor:install';

    protected $description = 'Prepara el motor: crea los roles base (admin, editor, user).';

    public function handle(): int
    {
        // La caché de Spatie puede sobrevivir a un migrate:fresh (p. ej. con
        // CACHE_STORE=file) y romper los chequeos de rol: fuera siempre.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (config('motor.auth.roles', ['admin', 'editor', 'user']) as $role) {
            Role::findOrCreate($role, 'web');
            $this->line("  Rol asegurado: {$role}");
        }

        $this->info('Motor instalado.');

        return self::SUCCESS;
    }
}
