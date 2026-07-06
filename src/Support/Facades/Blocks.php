<?php

namespace Edc\Core\Support\Facades;

use Edc\Core\Content\BlockTypeRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(string $typeClass)
 * @method static bool has(string $key)
 * @method static array keys()
 * @method static \Edc\Core\Content\BlockType get(string $key)
 * @method static array toArray()
 *
 * @see BlockTypeRegistry
 */
class Blocks extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BlockTypeRegistry::class;
    }
}
