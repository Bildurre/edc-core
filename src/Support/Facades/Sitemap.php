<?php

namespace Edc\Core\Support\Facades;

use Edc\Core\Content\SitemapRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void add(callable $provider)
 * @method static array entries()
 *
 * @see SitemapRegistry
 */
class Sitemap extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SitemapRegistry::class;
    }
}
