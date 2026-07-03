<?php

namespace Bgm\Core\Content;

use InvalidArgumentException;

/**
 * Registro de tipos de bloque (doc 03). El motor registra los de
 * presentación en su provider; cada juego añade los suyos en el boot de su
 * AppServiceProvider:
 *
 *     Blocks::register(CharactersGridBlock::class);
 *
 * Añadir un bloque = registrar la clase (+ su componente Vue en la app).
 */
class BlockTypeRegistry
{
    /** @var array<string, class-string<BlockType>> */
    protected array $types = [];

    /** @param class-string<BlockType> $typeClass */
    public function register(string $typeClass): void
    {
        if (! is_subclass_of($typeClass, BlockType::class)) {
            throw new InvalidArgumentException("{$typeClass} debe extender ".BlockType::class);
        }

        if ($typeClass::$key === '') {
            throw new InvalidArgumentException("{$typeClass} necesita una \$key.");
        }

        $this->types[$typeClass::$key] = $typeClass;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /** @return string[] */
    public function keys(): array
    {
        return array_keys($this->types);
    }

    public function get(string $key): BlockType
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Tipo de bloque desconocido: {$key}");
        }

        return app($this->types[$key]);
    }

    /** Paleta completa serializada (para el admin). */
    public function toArray(): array
    {
        return array_map(fn (string $class) => app($class)->toArray(), array_values($this->types));
    }
}
