<?php

namespace Bgm\Core\Content\BlockTypes;

use Bgm\Core\Content\BlockType;
use Bgm\Core\Content\Fields\Field;

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
            Field::richtext('body')->label('Texto')->translatable(),
            Field::text('button_text')->label('Texto del botón')->translatable()->required(),
            Field::text('button_url')->label('Enlace del botón')->translatable()->required(),
            Field::select('button_variant', [
                'primary' => 'Primario',
                'secondary' => 'Secundario',
            ])->label('Estilo del botón'),
        ];
    }
}
