<?php

namespace Edc\Core\Content\BlockTypes;

use Edc\Core\Content\BlockType;
use Edc\Core\Content\Fields\Field;

/**
 * Bloque de preguntas frecuentes (doc 03): la demo canónica del campo
 * `repeater` del DSL — una lista de filas {pregunta, respuesta}, ambas
 * traducibles, que el render pinta como acordeón.
 */
class FaqBlock extends BlockType
{
    public static string $key = 'faq';

    public string $name = 'Preguntas frecuentes';

    public string $icon = 'circle-help';

    public function fields(): array
    {
        return [
            Field::text('title')->label('Título')->translatable(),
            Field::text('subtitle')->label('Subtítulo')->translatable(),
            Field::repeater('items')->label('Preguntas')->min(1)->fields([
                Field::text('question')->label('Pregunta')->translatable()->required(),
                Field::richtext('answer')->label('Respuesta')->translatable(),
            ]),
        ];
    }
}
