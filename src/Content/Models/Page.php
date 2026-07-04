<?php

namespace Bgm\Core\Content\Models;

use Bgm\Core\Support\Concerns\HasFilters;
use Bgm\Core\Support\Concerns\HasPublishedState;
use Bgm\Core\Support\Concerns\ResolvesBySlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasTranslatableSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Página del CRM (doc 03): jerárquica, traducible, con SEO, plantilla y flag
 * imprimible. Su contenido son bloques ordenados (Block). Solo una página
 * puede ser home (lo garantiza PageService::setHome).
 */
class Page extends Model
{
    use HasFilters;
    use HasPublishedState;
    use HasTranslatableSlug;
    use HasTranslations;
    use ResolvesBySlug;
    use SoftDeletes;

    protected $table = 'pages';

    protected $fillable = [
        'title', 'description', 'slug', 'parent_id', 'order', 'template',
        'background_image', 'is_published', 'is_printable', 'meta_title', 'meta_description',
    ];

    public array $translatable = ['title', 'description', 'slug', 'meta_title', 'meta_description'];

    protected array $searchable = ['title'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_home' => 'boolean',
            'is_printable' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->orderBy('order');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    /** Páginas raíz (para el menú público y el árbol del admin). */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::createWithLocales(array_keys(config('motor.locales', ['es' => []])))
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }
}
