<?php

namespace Edc\Core\Content\BlockTypes;

use Edc\Core\Content\BlockType;
use Edc\Core\Content\Fields\Field;

/** Texto rico, opcionalmente con imagen a un lado (o rodeada por el texto). */
class TextBlock extends BlockType
{
    public static string $key = 'text';

    public string $name = 'Texto';

    public string $icon = 'text';

    public function fields(): array
    {
        return [
            Field::text('title')->label('Título')->translatable(),
            Field::richtext('body')->label('Texto')->translatable()->required(),
            Field::image('image')->label('Imagen')->translatable(),
            static::imagePositionField(),
        ];
    }
}
