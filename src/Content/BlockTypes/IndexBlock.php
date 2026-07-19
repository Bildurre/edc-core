<?php

namespace Edc\Core\Content\BlockTypes;

use Edc\Core\Content\BlockType;
use Edc\Core\Content\BlockTypeRegistry;
use Edc\Core\Content\Fields\Field;
use Edc\Core\Content\Models\Block;

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
            Field::textarea('subtitle')->label('Subtítulo')->translatable(),
            Field::boolean('numbered')->label('Numerado'),
        ];
    }

    public function resolveData(Block $block, string $locale): array
    {
        $registry = app(BlockTypeRegistry::class);

        $blocks = $block->page->blocks()
            ->indexable()
            ->where('order', '>', $block->order)
            ->whereKeyNot($block->id)
            ->orderBy('order')
            ->get()
            ->filter(fn (Block $other) => $registry->has($other->type));

        $entry = function (Block $other, int $depth) use ($registry, $locale): array {
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

            return ['id' => $other->id, 'label' => $label ?? $other->type, 'depth' => $depth];
        };

        // Jerarquía de un nivel (parent_id): cada padre con sus hijos
        // indentados justo debajo, para índices con sangría.
        $items = [];
        foreach ($blocks->whereNull('parent_id') as $parent) {
            $items[] = $entry($parent, 0);
            foreach ($blocks->where('parent_id', $parent->id) as $child) {
                $items[] = $entry($child, 1);
            }
        }

        return ['items' => $items];
    }
}
