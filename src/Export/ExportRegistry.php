<?php

namespace Edc\Core\Export;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Registro de modelos exportables a JSON. Cada juego registra los suyos en
 * el boot de su AppServiceProvider:
 *
 *     Exports::register('heroes', Hero::class);
 *
 * La clave es el segmento de la ruta (POST /api/admin/export/heroes) y el
 * nombre del fichero descargado (heroes-AAAA-MM-DD.json); el admin la
 * etiqueta por convención (export.models.<clave>).
 */
class ExportRegistry
{
    /** @var array<string, class-string<Model&ExportableContract>> */
    protected array $models = [];

    /** @param class-string<Model&ExportableContract> $modelClass */
    public function register(string $key, string $modelClass): void
    {
        if (! is_subclass_of($modelClass, ExportableContract::class)) {
            throw new InvalidArgumentException(
                "{$modelClass} debe implementar ".ExportableContract::class
            );
        }

        $this->models[$key] = $modelClass;
    }

    /** @return array<string, class-string<Model&ExportableContract>> */
    public function all(): array
    {
        return $this->models;
    }

    /** @return string[] */
    public function keys(): array
    {
        return array_keys($this->models);
    }

    public function has(string $key): bool
    {
        return isset($this->models[$key]);
    }

    /** @return class-string<Model&ExportableContract> */
    public function modelFor(string $key): string
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Modelo exportable desconocido: {$key}");
        }

        return $this->models[$key];
    }
}
