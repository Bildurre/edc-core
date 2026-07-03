<?php

namespace Bgm\Core\Console;

use Bgm\Core\Previews\PreviewRegistry;
use Bgm\Core\Previews\PreviewService;
use Illuminate\Console\Command;

/**
 * Gestión de previews en lote (doc 01), genérica: itera las entidades del
 * PreviewRegistry, sin nombres de modelo a fuego (a diferencia de choque).
 */
class PreviewManageCommand extends Command
{
    protected $signature = 'preview:manage
        {action : status | generate | regenerate | delete | clean}
        {--type= : Solo una entidad registrada (p. ej. character)}
        {--id= : Solo un id concreto}
        {--locale= : Solo un locale (por defecto, todos)}
        {--sync : Renderiza en el momento, sin cola}
        {--force : delete: no pide confirmación}
        {--dry-run : clean: solo lista, no borra}';

    protected $description = 'Gestiona las previews PNG: estado, generación, regeneración, borrado y limpieza de huérfanos.';

    public function handle(PreviewRegistry $registry, PreviewService $service): int
    {
        if (! config('motor.previews.enabled', true)) {
            $this->warn('Las previews están deshabilitadas (motor.previews.enabled).');
        }

        return match ($this->argument('action')) {
            'status' => $this->status($service),
            'generate' => $this->generate($registry, onlyMissing: true),
            'regenerate' => $this->generate($registry, onlyMissing: false),
            'delete' => $this->delete($registry),
            'clean' => $this->clean($service),
            default => $this->invalidAction(),
        };
    }

    protected function status(PreviewService $service): int
    {
        $rows = [];
        foreach ($service->status() as $key => $info) {
            $rows[] = [$key, $info['model'], $info['total'], $info['complete'], $info['pending']];
        }

        $this->table(['Tipo', 'Modelo', 'Total', 'Completas', 'Pendientes'], $rows);

        return self::SUCCESS;
    }

    protected function generate(PreviewRegistry $registry, bool $onlyMissing): int
    {
        $locales = $this->locales();
        $dispatched = 0;

        // Por clave de preview: --type=house solo toca ESA preview aunque el
        // modelo tenga otras (p. ej. house-counter).
        foreach ($this->entities($registry) as [$key, $entity]) {
            $pending = $onlyMissing
                ? array_values(array_filter($locales, fn ($l) => ! $entity->hasPreview($l, $key)))
                : $locales;

            if ($pending === []) {
                continue;
            }

            $entity->regeneratePreviews($pending, sync: (bool) $this->option('sync'), types: [$key]);
            $dispatched += count($pending);
        }

        $mode = $this->option('sync') ? 'renderizadas' : 'encoladas';
        $this->info("{$dispatched} previews {$mode}.");

        return self::SUCCESS;
    }

    protected function delete(PreviewRegistry $registry): int
    {
        if (! $this->option('force') && ! $this->confirm('¿Borrar las previews seleccionadas?')) {
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($this->entities($registry, withTrashed: true) as [$key, $entity]) {
            $entity->deletePreviews($key);
            $count++;
        }

        $this->info("Previews borradas de {$count} entidades.");

        return self::SUCCESS;
    }

    protected function clean(PreviewService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $orphans = $service->cleanOrphans($dryRun);

        foreach ($orphans as $file) {
            $this->line(($dryRun ? '[dry-run] ' : 'borrado: ').$file);
        }

        $this->info(count($orphans).' huérfanos'.($dryRun ? ' encontrados.' : ' eliminados.'));

        return self::SUCCESS;
    }

    /** Pares [clave, entidad] a procesar según --type y --id. */
    protected function entities(PreviewRegistry $registry, bool $withTrashed = false): iterable
    {
        $keys = $this->option('type') ? [$this->option('type')] : $registry->keys();

        foreach ($keys as $key) {
            $model = $registry->modelFor($key);
            $query = $model::query();

            if ($withTrashed && method_exists($model, 'bootSoftDeletes')) {
                $query->withTrashed();
            }

            if ($id = $this->option('id')) {
                $query->whereKey($id);
            }

            foreach ($query->cursor() as $entity) {
                yield [$key, $entity];
            }
        }
    }

    protected function locales(): array
    {
        return $this->option('locale')
            ? [$this->option('locale')]
            : array_keys(config('motor.locales', []));
    }

    protected function invalidAction(): int
    {
        $this->error('Acción no reconocida. Usa: status | generate | regenerate | delete | clean.');

        return self::FAILURE;
    }
}
