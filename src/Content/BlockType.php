<?php

namespace Edc\Core\Content;

use Edc\Core\Content\Fields\Field;
use Edc\Core\Content\Models\Block;
use Edc\Core\Support\HtmlSanitizer;

/**
 * La pieza central del CRM (doc 03): un tipo de bloque se declara UNA vez y
 * de aquí sale todo — el formulario del admin (fields), la validación
 * (derivada del esquema), la localización de valores y los datos extra del
 * render (resolveData). El motor trae los de presentación; cada juego
 * registra los suyos:
 *
 *     Blocks::register(CharactersGridBlock::class);
 */
abstract class BlockType
{
    /** Clave única del tipo (la de la API y el registro de componentes Vue). */
    public static string $key = '';

    /** Etiqueta para la paleta del admin (castellano; el admin puede traducirla). */
    public string $name = '';

    /** Icono lucide para la paleta. */
    public string $icon = 'square';

    /** 'presentation' (motor) o 'data' (del juego, consulta sus modelos). */
    public string $category = 'presentation';

    /**
     * Esquema de campos propios del tipo (DC-08).
     *
     * @return Field[]
     */
    abstract public function fields(): array;

    /**
     * Datos extra para el render público (los bloques-con-datos del juego
     * consultan AQUÍ sus modelos; los de presentación no necesitan nada).
     */
    public function resolveData(Block $block, string $locale): array
    {
        return [];
    }

    /** Clave del componente Vue en el blockRegistry de la app (= key). */
    public function component(): string
    {
        return static::$key;
    }

    /**
     * Campos comunes a TODOS los bloques (los aplica el envoltorio genérico
     * del render): alineación del texto, anchura del contenido y color de
     * fondo.
     *
     * @return Field[]
     */
    public static function commonFields(): array
    {
        return [
            Field::select('align', [
                'left' => 'Izquierda',
                'center' => 'Centrado',
                'right' => 'Derecha',
                'justify' => 'Justificado',
            ])->label('Alineación')->default('left'),
            // Anchura del contenido del bloque: coherencia entre bloques y
            // entre páginas. Por defecto 'wide' (~1200px).
            Field::select('width', [
                'wide' => 'Ancho',
                'full' => 'Ancho completo',
                'narrow' => 'Estrecho',
            ])->label('Anchura del contenido')->default('wide'),
            Field::color('background')->label('Color de fondo'),
        ];
    }

    /**
     * Campo estándar "posición de la imagen": columnas (izquierda/derecha),
     * arriba/abajo, o flotada con el texto rodeándola (clear-*). Cualquier
     * tipo con un campo image puede reutilizarlo.
     */
    public static function imagePositionField(): Field
    {
        return Field::select('image_position', [
            'top' => 'Arriba',
            'left' => 'Izquierda',
            'right' => 'Derecha',
            'bottom' => 'Abajo',
            'clear-left' => 'Izquierda (el texto la rodea)',
            'clear-right' => 'Derecha (el texto la rodea)',
        ])->label('Posición de la imagen')->default('top');
    }

    /**
     * Campos estándar del layout de imagen en columnas: posición + modo de
     * escalado (el alto lo marca el texto de al lado) + reparto de columnas
     * izquierda:derecha. Cualquier tipo con un campo image los reutiliza.
     *
     * @return Field[]
     */
    public static function imageLayoutFields(): array
    {
        return [
            static::imagePositionField(),
            Field::select('image_fit', [
                'contain' => 'Contener (entera, sin deformar)',
                'cover' => 'Cubrir (recorta para llenar)',
                'fill' => 'Rellenar (deforma para llenar)',
            ])->label('Escalado de la imagen (en columnas)')->default('contain'),
            Field::select('image_columns', [
                '1:1' => '1 : 1',
                '1:2' => '1 : 2',
                '2:1' => '2 : 1',
                '1:3' => '1 : 3',
                '3:1' => '3 : 1',
                '2:3' => '2 : 3',
                '3:2' => '3 : 2',
                '1:4' => '1 : 4',
                '4:1' => '4 : 1',
                '3:4' => '3 : 4',
                '4:3' => '4 : 3',
            ])->label('Reparto de columnas (izquierda : derecha)')->default('2:3'),
        ];
    }

    /**
     * Esquema completo: campos del tipo + comunes.
     *
     * @return Field[]
     */
    public function allFields(): array
    {
        return [...$this->fields(), ...self::commonFields()];
    }

    /** Reglas de validación de `settings`, derivadas del esquema. */
    public function rules(): array
    {
        $locales = array_keys(config('motor.locales', []));
        $rules = ['settings' => ['nullable', 'array']];

        foreach ($this->allFields() as $field) {
            $rules += $field->rules($locales);
        }

        return $rules;
    }

    /** Sanea los campos richtext (DC-09) antes de guardar, anidados incluidos. */
    public function sanitizeSettings(array $settings): array
    {
        return $this->sanitizeFields($this->allFields(), $settings);
    }

    /** @param  Field[]  $fields */
    protected function sanitizeFields(array $fields, array $settings): array
    {
        $sanitizer = app(HtmlSanitizer::class);

        foreach ($fields as $field) {
            if (! isset($settings[$field->key])) {
                continue;
            }
            $value = $settings[$field->key];

            // Anidados: recursión sobre el objeto (group) o cada fila (repeater).
            if ($field->type === 'group' && is_array($value)) {
                $settings[$field->key] = $this->sanitizeFields($field->fields, $value);

                continue;
            }
            if ($field->type === 'repeater' && is_array($value)) {
                $settings[$field->key] = array_map(
                    fn ($row) => is_array($row) ? $this->sanitizeFields($field->fields, $row) : $row,
                    array_values($value),
                );

                continue;
            }

            if ($field->type !== 'richtext') {
                continue;
            }
            if ($field->translatable && is_array($value)) {
                foreach ($value as $locale => $html) {
                    $settings[$field->key][$locale] = $sanitizer->clean($html);
                }
            } elseif (is_string($value)) {
                $settings[$field->key] = $sanitizer->clean($value);
            }
        }

        return $settings;
    }

    /**
     * Valores localizados para el render público: los campos traducibles
     * eligen el locale pedido (con fallback al default) y el resto rellena
     * su default si falta. Los anidados (group/repeater) se localizan por
     * dentro.
     */
    public function localizeSettings(?array $settings, string $locale): array
    {
        return $this->localizeFields($this->allFields(), $settings ?? [], $locale);
    }

    /** @param  Field[]  $fields */
    protected function localizeFields(array $fields, array $settings, string $locale): array
    {
        $default = config('motor.default_locale', 'es');
        $out = [];

        foreach ($fields as $field) {
            $value = $settings[$field->key] ?? null;

            if ($field->type === 'group') {
                $out[$field->key] = $this->localizeFields($field->fields, is_array($value) ? $value : [], $locale);

                continue;
            }
            if ($field->type === 'repeater') {
                $out[$field->key] = array_map(
                    fn ($row) => $this->localizeFields($field->fields, is_array($row) ? $row : [], $locale),
                    is_array($value) ? array_values($value) : [],
                );

                continue;
            }

            if ($field->translatable && is_array($value)) {
                $value = $value[$locale] ?? $value[$default] ?? null;
            }

            $out[$field->key] = $value ?? $field->default;
        }

        return $out;
    }

    /** Serialización para la paleta del admin (GET /admin/block-types). */
    public function toArray(): array
    {
        return [
            'key' => static::$key,
            'name' => $this->name !== '' ? $this->name : static::$key,
            'icon' => $this->icon,
            'category' => $this->category,
            'component' => $this->component(),
            'fields' => array_map(fn (Field $field) => $field->toArray(), $this->fields()),
            'common' => array_map(fn (Field $field) => $field->toArray(), self::commonFields()),
        ];
    }
}
