<?php

namespace Edc\Core\Content\Fields;

/**
 * DSL de campos (DC-08): describe QUÉ edita el admin en un tipo de bloque.
 * Del esquema salen solas tres cosas: el formulario del BlockEditor (se
 * serializa a la API), la validación en servidor y la localización de los
 * valores al renderizar. Tipos base: text, textarea, richtext, number,
 * boolean, select, color, image; anidados: group (objeto), repeater (lista
 * de filas) y entity (referencia por id a una entidad del juego).
 * Extensible con make() para tipos a medida.
 */
class Field
{
    /** @param array<string, mixed> $options */
    protected function __construct(
        public readonly string $key,
        public readonly string $type,
        public string $label = '',
        public bool $translatable = false,
        public bool $required = false,
        public mixed $default = null,
        /** Para select: valor => etiqueta. */
        public array $options = [],
        public ?int $min = null,
        public ?int $max = null,
        /** Subcampos de group/repeater. @var Field[] */
        public array $fields = [],
        /** Para entity: endpoint de opciones del admin (id + name). */
        public ?string $optionsUrl = null,
    ) {}

    public static function text(string $key): self
    {
        return new self($key, 'text');
    }

    public static function textarea(string $key): self
    {
        return new self($key, 'textarea');
    }

    /** Texto rico TipTap (DC-09): se sanea en servidor al guardar. */
    public static function richtext(string $key): self
    {
        return new self($key, 'richtext');
    }

    public static function number(string $key): self
    {
        return new self($key, 'number');
    }

    public static function boolean(string $key): self
    {
        return new self($key, 'boolean', default: false);
    }

    /** @param array<string, string>|string[] $options valor => etiqueta (o lista de valores). */
    public static function select(string $key, array $options): self
    {
        $normalized = array_is_list($options)
            ? array_combine($options, $options)
            : $options;

        $field = new self($key, 'select', options: $normalized);
        $field->default = array_key_first($normalized);

        return $field;
    }

    public static function color(string $key): self
    {
        return new self($key, 'color');
    }

    /** Imagen (MediaLibrary): el valor guardado es la URL pública. */
    public static function image(string $key): self
    {
        return new self($key, 'image');
    }

    /** Grupo de subcampos: en settings queda un objeto {subclave: valor}. */
    public static function group(string $key): self
    {
        return new self($key, 'group');
    }

    /**
     * Lista de filas, cada una con los mismos subcampos: en settings queda
     * un array de objetos. min/max acotan el número de filas.
     */
    public static function repeater(string $key): self
    {
        return new self($key, 'repeater');
    }

    /**
     * Referencia a una entidad del juego: en settings queda su id; el admin
     * la elige con un buscador alimentado por el endpoint de opciones
     * (p. ej. '/admin/houses/options', shape {data: [{id, name: {locale}}]}).
     * El resolveData del bloque carga el modelo al renderizar.
     */
    public static function entity(string $key, string $optionsUrl): self
    {
        return new self($key, 'entity', optionsUrl: $optionsUrl);
    }

    /** Tipo a medida del juego (el admin necesitará saber pintarlo). */
    public static function make(string $key, string $type): self
    {
        return new self($key, $type);
    }

    // --- Modificadores encadenables ---

    /** El valor se guarda por locale ({es: ..., eu: ...}). */
    public function translatable(bool $translatable = true): self
    {
        $this->translatable = $translatable;

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    /** Etiqueta legible (castellano por defecto; el admin puede traducirla). */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function min(int $min): self
    {
        $this->min = $min;

        return $this;
    }

    public function max(int $max): self
    {
        $this->max = $max;

        return $this;
    }

    /**
     * Subcampos de un group/repeater.
     *
     * @param  Field[]  $fields
     */
    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    // --- Derivados del esquema ---

    /**
     * Reglas de validación Laravel para este campo dentro de `settings`.
     * El prefijo permite anidar (group: 'settings.clave.'; repeater:
     * 'settings.clave.*.').
     *
     * @param  string[]  $locales
     * @return array<string, array<int, mixed>>
     */
    public function rules(array $locales, string $prefix = 'settings.'): array
    {
        $path = $prefix.$this->key;
        $requirement = $this->required ? 'required' : 'nullable';

        // Anidados: el propio campo es array; los subcampos se prefijan.
        if ($this->type === 'group' || $this->type === 'repeater') {
            $rules = [$path => array_values(array_filter([
                $requirement,
                'array',
                $this->type === 'repeater' && $this->min !== null ? "min:{$this->min}" : null,
                $this->type === 'repeater' && $this->max !== null ? "max:{$this->max}" : null,
            ]))];
            $childPrefix = $this->type === 'repeater' ? "{$path}.*." : "{$path}.";
            foreach ($this->fields as $field) {
                $rules += $field->rules($locales, $childPrefix);
            }

            return $rules;
        }

        $base = match ($this->type) {
            'number' => array_values(array_filter([
                'integer',
                $this->min !== null ? "min:{$this->min}" : null,
                $this->max !== null ? "max:{$this->max}" : null,
            ])),
            'boolean' => ['boolean'],
            'select' => ['string', 'in:'.implode(',', array_keys($this->options))],
            'color' => ['string', 'max:32'],
            'entity' => ['integer'],
            default => ['string'],
        };

        if (! $this->translatable) {
            return [$path => [$requirement, ...$base]];
        }

        // Traducible: objeto locale => valor; el default locale manda si es required.
        $rules = [$path => [$requirement, 'array']];
        $default = config('motor.default_locale', 'es');
        foreach ($locales as $locale) {
            $rules["{$path}.{$locale}"] = [
                $this->required && $locale === $default ? 'required' : 'nullable',
                ...$base,
            ];
        }

        return $rules;
    }

    /** Serialización para la API (el BlockEditor pinta el formulario con esto). */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label !== '' ? $this->label : $this->key,
            'translatable' => $this->translatable,
            'required' => $this->required,
            'default' => $this->default,
            'options' => $this->options === [] ? null : $this->options,
            'min' => $this->min,
            'max' => $this->max,
            'fields' => $this->fields === []
                ? null
                : array_map(fn (Field $field) => $field->toArray(), $this->fields),
            'options_url' => $this->optionsUrl,
        ];
    }
}
