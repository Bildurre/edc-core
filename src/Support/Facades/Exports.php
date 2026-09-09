<?php

namespace Edc\Core\Support\Facades;

use Edc\Core\Export\ExportRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(string $key, string $modelClass)
 * @method static array all()
 * @method static array keys()
 * @method static bool has(string $key)
 * @method static string modelFor(string $key)
 *
 * @see ExportRegistry
 */
class Exports extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ExportRegistry::class;
    }
}
