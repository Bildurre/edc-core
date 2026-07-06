<?php

namespace Edc\Core\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloque de una página (doc 03). Deliberadamente simple: el TIPO (BlockType
 * del registro) da la forma; todos los valores de sus campos —traducibles
 * incluidos— viven en `settings` JSON. El motor no añade columnas por tipo.
 */
class Block extends Model
{
    protected $table = 'blocks';

    protected $fillable = [
        'page_id', 'parent_id', 'type', 'order',
        'settings', 'is_printable', 'is_indexable',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'order' => 'integer',
            'is_printable' => 'boolean',
            'is_indexable' => 'boolean',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function scopePrintable($query)
    {
        return $query->where('is_printable', true);
    }

    public function scopeIndexable($query)
    {
        return $query->where('is_indexable', true);
    }
}
