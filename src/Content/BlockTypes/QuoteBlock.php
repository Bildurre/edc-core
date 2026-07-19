<?php

namespace Edc\Core\Content\BlockTypes;

use Edc\Core\Content\BlockType;
use Edc\Core\Content\Fields\Field;

/** Cita destacada con autor, con retrato opcional. */
class QuoteBlock extends BlockType
{
    public static string $key = 'quote';

    public string $name = 'Cita';

    public string $icon = 'quote';

    public function fields(): array
    {
        return [
            Field::text('title')->label('Título')->translatable(),
            Field::textarea('subtitle')->label('Subtítulo')->translatable(),
            Field::richtext('quote')->label('Cita')->translatable()->required(),
            Field::text('author')->label('Autor')->translatable(),
            Field::select('author_align', [
                'left' => 'Izquierda',
                'center' => 'Centrado',
                'right' => 'Derecha',
            ])->label('Alineación del autor')->default('left'),
            Field::image('image')->label('Imagen (retrato del autor)')->translatable(),
        ];
    }
}
