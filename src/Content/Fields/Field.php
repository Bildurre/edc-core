<?php

namespace Bgm\Core\Content\Fields;

/**
 * DSL de campos (DC-08): describe QUÉ edita el admin en un tipo de bloque.
 * Del esquema salen solas tres cosas: el formulario del BlockEditor (se
 * serializa a la API), la validación en servidor y la localización de los
 * valores al renderizar. Tipos base: text, textarea, richtext, number,
 * boolean, select, color, image. Extensible con make() para tipos a medida.
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

    // --- Derivados del esquema ---

    /**
     * Reglas de validación Laravel para este campo dentro de `settings`.
     *
     * @param  string[]  $locales
     * @return array<string, array<int, mixed>>
     */
    public function rules(array $locales): array
    {
        $base = match ($this->type) {
            'number' => array_values(array_filter([
                'integer',
                $this->min !== null ? "min:{$this->min}" : null,
                $this->max !== null ? "max:{$this->max}" : null,
            ])),
            'boolean' => ['boolean'],
            'select' => ['string', 'in:'.implode(',', array_keys($this->options))],
            'color' => ['string', 'max:32'],
            default => ['string'],
        };

        $requirement = $this->required ? 'required' : 'nullable';

        if (! $this->translatable) {
            return ["settings.{$this->key}" => [$requirement, ...$base]];
        }

        // Traducible: objeto locale => valor; el default locale manda si es required.
        $rules = ["settings.{$this->key}" => [$requirement, 'array']];
        $default = config('motor.default_locale', 'es');
        foreach ($locales as $locale) {
            $rules["settings.{$this->key}.{$locale}"] = [
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
        ];
    }
}
