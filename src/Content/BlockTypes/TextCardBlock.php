<?php

namespace Edc\Core\Content\BlockTypes;

use Edc\Core\Content\BlockType;
use Edc\Core\Content\Fields\Field;

/** Tarjeta destacada: etiqueta + título + texto, con imagen opcional. */
class TextCardBlock extends BlockType
{
    public static string $key = 'text-card';

    public string $name = 'Tarjeta de texto';

    public string $icon = 'id-card';

    public function fields(): array
    {
        return [
            Field::text('label')->label('Etiqueta')->translatable(),
            Field::text('title')->label('Título')->translatable(),
            Field::richtext('body')->label('Texto')->translatable()->required(),
            Field::image('image')->label('Imagen')->translatable(),
            static::imagePositionField(),
        ];
    }
}
