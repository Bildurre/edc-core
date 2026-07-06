<?php

namespace Edc\Core\Icons\Models;

use Edc\Core\Media\Concerns\HasImage;
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

    protected $table = 'icons';

    protected $fillable = ['name', 'slug'];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
