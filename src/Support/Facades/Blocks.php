<?php

namespace Bgm\Core\Support\Facades;

use Bgm\Core\Content\BlockTypeRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(string $typeClass)
 * @method static bool has(string $key)
 * @method static array keys()
 * @method static \Bgm\Core\Content\BlockType get(string $key)
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
