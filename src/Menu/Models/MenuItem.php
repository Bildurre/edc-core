<?php

namespace Edc\Core\Menu\Models;

use Edc\Core\Content\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item del menú configurable de la web pública. Dos tipos: 'page' (apunta a
 * una página del CRM) y 'route' (una ruta propia del juego, por route_key).
 * Sin grupos: una página con hijas (pages.parent_id) hace de desplegable.
 * `parent_id` es solo para anidar una ruta bajo una página raíz — las
 * páginas siempre lo llevan a null, su jerarquía sale de `pages.parent_id`
 * (ver MenuService, que construye el árbol derivándola).
 */
class MenuItem extends Model
{
    protected $table = 'menu_items';

    protected $fillable = [
        'parent_id', 'order', 'is_visible', 'type', 'page_id', 'route_key',
    ];

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
