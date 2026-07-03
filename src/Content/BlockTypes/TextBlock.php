<?php

namespace Bgm\Core\Content\BlockTypes;

use Bgm\Core\Content\BlockType;
use Bgm\Core\Content\Fields\Field;

/** Texto rico, opcionalmente con imagen a un lado. */
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
            Field::image('image')->label('Imagen'),
            Field::select('image_position', [
                'top' => 'Arriba',
                'left' => 'Izquierda',
                'right' => 'Derecha',
                'bottom' => 'Abajo',
            ])->label('Posición de la imagen'),
        ];
    }
}
