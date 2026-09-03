<?php

namespace Edc\Core\Content\BlockTypes;

use Edc\Core\Content\BlockType;
use Edc\Core\Content\Fields\Field;

/**
 * Pestañas (doc 03): el único bloque CONTENEDOR del motor. Declara sus
 * pestañas en un repetidor (texto, icono lucide y ancla opcional) y su
 * contenido son sus bloques HIJOS (parent_id): el hijo n.º N es el
 * contenido de la pestaña N, en el orden del gestor; los descendientes de
 * ese hijo van con él en la misma pestaña. Un hijo puede ser cualquier
 * bloque, también los de datos del juego (un índice de entidad por
 * pestaña, por ejemplo).
 *
 * En la web solo se monta la pestaña activa (sus filtros de barra derecha,
 * su paginación); la URL enlaza una pestaña por su ancla (`#ancla`, o
 * `#tab-{id}-{n}` si no tiene). En el PDF los hijos se imprimen en
 * secuencia tras el padre, cada uno precedido por el nombre de su pestaña.
 *
 * El gestor del admin avisa si hay pestañas sin bloque o bloques de más.
 */
class TabsBlock extends BlockType
{
    public static string $key = 'tabs';

    public string $name = 'Pestañas';

    public string $icon = 'panels-top-left';

    public function fields(): array
    {
        return [
            Field::text('title')->label('Título')->translatable(),
            Field::textarea('subtitle')->label('Subtítulo')->translatable(),
            Field::repeater('tabs')->label('Pestañas')->min(1)->fields([
                Field::text('label')->label('Texto')->translatable()->required()->row('tab'),
                // Ancla de la URL para enlazar la pestaña (#ancla); sin ella
                // el front usa tab-{id}-{n}.
                Field::text('anchor')->label('Ancla (para enlazar)')->row('tab'),
                // Icono lucide: el admin lo elige en el catálogo curado del
                // motor (IconPicker del ui, a todo el ancho de la fila) y se
                // guarda su nombre kebab-case.
                Field::make('icon', 'icon')->label('Icono'),
            ]),
        ];
    }
}
