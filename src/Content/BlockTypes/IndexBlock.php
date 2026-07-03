<?php

namespace Bgm\Core\Content\BlockTypes;

use Bgm\Core\Content\BlockType;
use Bgm\Core\Content\BlockTypeRegistry;
use Bgm\Core\Content\Fields\Field;
use Bgm\Core\Content\Models\Block;

/**
 * Índice automático: enlaza a los bloques POSTERIORES de la página marcados
 * como indexables. La etiqueta de cada entrada es su primer texto traducible
 * con valor (o la clave del tipo). Anclas: #block-{id} (las pone PageView).
 */
class IndexBlock extends BlockType
{
    public static string $key = 'index';

    public string $name = 'Índice automático';

    public string $icon = 'list';

    public function fields(): array
    {
        return [
            Field::text('title')->label('Título')->translatable(),
            Field::boolean('numbered')->label('Numerado'),
        ];
    }

    public function resolveData(Block $block, string $locale): array
    {
        $registry = app(BlockTypeRegistry::class);

        $items = $block->page->blocks()
            ->indexable()
            ->where('order', '>', $block->order)
            ->whereKeyNot($block->id)
            ->get()
            ->filter(fn (Block $other) => $registry->has($other->type))
            ->map(function (Block $other) use ($registry, $locale) {
                $type = $registry->get($other->type);
                $settings = $type->localizeSettings($other->settings, $locale);

                $label = null;
                foreach ($type->fields() as $field) {
                    if ($field->translatable && in_array($field->type, ['text', 'richtext'], true)) {
                        $value = trim(strip_tags((string) ($settings[$field->key] ?? '')));
                        if ($value !== '') {
                            $label = mb_substr($value, 0, 80);

                            break;
                        }
                    }
                }

                return ['id' => $other->id, 'label' => $label ?? $other->type];
            })
            ->values()
            ->all();

        return ['items' => $items];
    }
}
