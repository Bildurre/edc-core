<?php

namespace Bgm\Core\Previews;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Registro de entidades renderizables. Cada juego registra las suyas en el
 * boot de su AppServiceProvider:
 *
 *     Previews::register('character', Character::class);
 *
 * La clave es el segmento de URL de la ruta de render (/_render/character/1)
 * y la carpeta de almacenamiento (previews/character/...).
 *
 * Un modelo puede registrarse con MÁS de una clave para tener varias
 * previews (la primera registrada es la por defecto):
 *
 *     Previews::register('house', House::class);         // token 40 mm
 *     Previews::register('house-counter', House::class); // contador 25 mm
 */
class PreviewRegistry
{
    /** @var array<string, class-string<Model&PreviewableContract>> */
    protected array $entities = [];

    /** @param class-string<Model&PreviewableContract> $modelClass */
    public function register(string $key, string $modelClass): void
    {
        if (! is_subclass_of($modelClass, PreviewableContract::class)) {
            throw new InvalidArgumentException(
                "{$modelClass} debe implementar ".PreviewableContract::class
            );
        }

        $this->entities[$key] = $modelClass;
    }

    /** @return array<string, class-string<Model&PreviewableContract>> */
    public function all(): array
    {
        return $this->entities;
    }

    /** @return string[] */
    public function keys(): array
    {
        return array_keys($this->entities);
    }

    public function has(string $key): bool
    {
        return isset($this->entities[$key]);
    }

    /** @return class-string<Model&PreviewableContract> */
    public function modelFor(string $key): string
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Entidad renderizable desconocida: {$key}");
        }

        return $this->entities[$key];
    }

    /** Clave POR DEFECTO de un modelo: la primera registrada (o null). */
    public function keyFor(Model $model): ?string
    {
        $key = array_search($model::class, $this->entities, true);

        return $key === false ? null : $key;
    }

    /**
     * Todas las claves registradas para un modelo (sus previews).
     *
     * @return string[]
     */
    public function keysFor(Model $model): array
    {
        return array_keys($this->entities, $model::class, true);
    }
}
