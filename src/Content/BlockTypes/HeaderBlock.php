<?php

namespace Bgm\Core\Content\BlockTypes;

use Bgm\Core\Content\BlockType;
use Bgm\Core\Content\Fields\Field;

/** Cabecera de sección: título + subtítulo. */
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
        ];
    }
}
