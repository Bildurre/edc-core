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
            Field::textarea('subtitle')->label('Subtítulo')->translatable(),
            Field::richtext('body')->label('Texto')->translatable(),
            // El botón, en dos filas mientras quepan (Field::row; en un
            // modal angosto apilan): texto+enlace y grande+alineación+estilo.
            Field::text('button_text')->label('Texto del botón')->translatable()->required()
                ->row('button_main'),
            Field::text('button_url')->label('Enlace del botón')->translatable()->required()
                ->row('button_main'),
            Field::boolean('button_large')->label('Botón grande')->row('button_layout'),
            // En formato estrecho el botón va SIEMPRE centrado (lo hace el
            // grid del ui): esta alineación aplica de ahí para arriba.
            Field::select('button_align', [
                'left' => 'Izquierda',
                'center' => 'Centrado',
                'right' => 'Derecha',
            ])->label('Alineación del botón')->default('left')->row('button_layout'),
            Field::select('button_variant', [
                'primary' => 'Normal (fondo de acento)',
                'secondary' => 'Inverso (fondo del fondo)',
            ])->label('Estilo del botón')->row('button_layout'),
            Field::image('image')->label('Imagen')->translatable(),
            ...static::imageLayoutFields(),
        ];
    }
}
