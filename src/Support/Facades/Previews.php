<?php

namespace Edc\Core\Support\Facades;

use Edc\Core\Previews\PreviewRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(string $key, string $modelClass)
 * @method static array all()
 * @method static array keys()
 * @method static bool has(string $key)
 * @method static string modelFor(string $key)
 * @method static ?string keyFor(\Illuminate\Database\Eloquent\Model $model)
 *
 * @see PreviewRegistry
 */
class Previews extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PreviewRegistry::class;
    }
}
