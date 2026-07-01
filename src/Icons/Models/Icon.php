<?php

namespace Bgm\Core\Icons\Models;

use Bgm\Core\Media\Concerns\HasImage;
use Bgm\Core\Support\Concerns\ResolvesBySlug;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Icono de la biblioteca del juego. Nombre + slug + imagen (SVG/PNG). Se lista
 * para el selector del editor WYSIWYG y se inserta en el texto como <img>.
 */
class Icon extends Model implements HasMedia
{
    use HasImage;
    use HasSlug;
    use ResolvesBySlug;

    protected $table = 'icons';

    protected $fillable = ['name', 'slug'];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
