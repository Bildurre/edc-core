<?php

namespace Edc\Core\Content\BlockTypes;

use Edc\Core\Content\BlockType;
use Edc\Core\Content\Fields\Field;

/** Cabecera de sección: título + subtítulo, con imagen de banner opcional. */
class HeaderBlock extends BlockType
{
    public static string $key = 'header';

    public string $name = 'Cabecera';

    public string $icon = 'heading';

    public function fields(): array
    {
        return [
            Field::text('title')->label('Título')->translatable()->required(),
            Field::text('subtitle')->label('Subtítulo')->translatable(),
            Field::image('image')->label('Imagen (banner)')->translatable(),
        ];
    }
}
