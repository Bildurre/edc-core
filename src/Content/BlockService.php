<?php

namespace Edc\Core\Content;

use Edc\Core\Content\Models\Block;
use Edc\Core\Content\Models\Page;

/**
 * CRUD y reorden de bloques (doc 03). La validación de `settings` la deriva
 * el controlador del esquema del BlockType; aquí solo se sanea el texto rico
 * (DC-09), se coloca al final por defecto y se invalida la caché de la página.
 */
class BlockService
{
    public function __construct(
        protected BlockTypeRegistry $registry,
        protected PageService $pages,
    ) {}

    public function create(Page $page, string $type, array $data): Block
    {
        $blockType = $this->registry->get($type);

        $block = new Block([
            'type' => $type,
            'settings' => $blockType->sanitizeSettings($data['settings'] ?? []),
            'order' => $page->blocks()->max('order') + 1,
            'is_printable' => $data['is_printable'] ?? true,
            'is_indexable' => $data['is_indexable'] ?? true,
        ]);
        $block->page()->associate($page);
        $block->save();

        $this->pages->forget($block);

        return $block;
    }

    public function update(Block $block, array $data): Block
    {
        $blockType = $this->registry->get($block->type);

        if (array_key_exists('settings', $data)) {
            $block->settings = $blockType->sanitizeSettings($data['settings'] ?? []);
        }
        foreach (['is_printable', 'is_indexable'] as $field) {
            if (array_key_exists($field, $data)) {
                $block->{$field} = $data[$field];
            }
        }
        $block->save();

        $this->pages->forget($block);

        return $block;
    }

    public function delete(Block $block): void
    {
        $block->delete();
        $this->pages->forget($block);
    }

    /** La lista de ids marca el orden (0, 1, 2…). */
    public function reorder(Page $page, array $ids): void
    {
        foreach (array_values($ids) as $index => $id) {
            $page->blocks()->whereKey($id)->update(['order' => $index]);
        }
        $this->pages->forget($page);
    }
}
