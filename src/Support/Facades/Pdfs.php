<?php

namespace Edc\Core\Support\Facades;

use Edc\Core\Pdf\PdfExportRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(string $type, string $exportClass)
 * @method static void layout(string $key, array $preset)
 * @method static bool has(string $type)
 * @method static array types()
 * @method static \Edc\Core\Pdf\PdfExportContract get(string $type)
 *
 * @see PdfExportRegistry
 */
class Pdfs extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PdfExportRegistry::class;
    }
}
