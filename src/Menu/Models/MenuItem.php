<?php

namespace Edc\Core\Menu\Models;

use Edc\Core\Content\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * Item del menú configurable de la web pública. Tres tipos: 'page' (apunta a
 * una página del CRM), 'route' (una ruta propia del juego, por route_key) y
 * 'group' (carpeta del admin, con label traducible, nunca la sincroniza
 * MenuSync). Un solo nivel: los grupos no pueden colgar de otro grupo.
 */
class MenuItem extends Model
{
    use HasTranslations;

    protected $table = 'menu_items';

    protected $fillable = [
        'parent_id', 'order', 'is_visible', 'type', 'page_id', 'route_key', 'label',
    ];

    public array $translatable = ['label'];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->ordered();
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }
}
