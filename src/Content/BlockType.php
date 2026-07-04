<?php

namespace Bgm\Core\Content;

use Bgm\Core\Content\Fields\Field;
use Bgm\Core\Content\Models\Block;
use Bgm\Core\Support\HtmlSanitizer;

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
            ])->label('Alineación'),
            // Anchura del contenido del bloque: coherencia entre bloques y
            // entre páginas (la columna de lectura la decide cada bloque).
            Field::select('width', [
                'wide' => 'Ancho',
                'full' => 'Ancho completo',
                'narrow' => 'Estrecho',
            ])->label('Anchura del contenido'),
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

    /** Sanea los campos richtext (DC-09) antes de guardar. */
    public function sanitizeSettings(array $settings): array
    {
        $sanitizer = app(HtmlSanitizer::class);

        foreach ($this->allFields() as $field) {
            if ($field->type !== 'richtext' || ! isset($settings[$field->key])) {
                continue;
            }

            if ($field->translatable && is_array($settings[$field->key])) {
                foreach ($settings[$field->key] as $locale => $html) {
                    $settings[$field->key][$locale] = $sanitizer->clean($html);
                }
            } elseif (is_string($settings[$field->key])) {
                $settings[$field->key] = $sanitizer->clean($settings[$field->key]);
            }
        }

        return $settings;
    }

    /**
     * Valores localizados para el render público: los campos traducibles
     * eligen el locale pedido (con fallback al default) y el resto rellena
     * su default si falta.
     */
    public function localizeSettings(?array $settings, string $locale): array
    {
        $settings ??= [];
        $default = config('motor.default_locale', 'es');
        $out = [];

        foreach ($this->allFields() as $field) {
            $value = $settings[$field->key] ?? null;

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
