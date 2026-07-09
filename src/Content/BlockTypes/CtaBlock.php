<?php

namespace Edc\Core\Content\BlockTypes;

use Edc\Core\Content\BlockType;
use Edc\Core\Content\Fields\Field;

/** Llamada a la acción: texto + botón con enlace. */
class CtaBlock extends BlockType
{
    public static string $key = 'cta';

    public string $name = 'Llamada a la acción';

    public string $icon = 'link';

    public function fields(): array
    {
        return [
            Field::text('title')->label('Título')->translatable(),
            Field::text('subtitle')->label('Subtítulo')->translatable(),
            Field::richtext('body')->label('Texto')->translatable(),
            Field::text('button_text')->label('Texto del botón')->translatable()->required(),
            Field::text('button_url')->label('Enlace del botón')->translatable()->required(),
            Field::select('button_variant', [
                'primary' => 'Normal (fondo de acento)',
                'secondary' => 'Inverso (fondo del fondo)',
            ])->label('Estilo del botón'),
            Field::image('image')->label('Imagen')->translatable(),
            ...static::imageLayoutFields(),
        ];
    }
}
