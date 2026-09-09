<?php

namespace Edc\Core\Export;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Construye el JSON de un modelo exportable: cabecera (modelo, fecha,
 * idiomas, campos, cuenta) + `items`, cada uno con SOLO los campos elegidos
 * y en el orden del catálogo del modelo (exportFields()).
 */
class JsonExporter
{
    /** @var class-string<Model&ExportableContract> */
    protected string $modelClass;

    /** @var string[] */
    protected array $fields;

    protected ExportLocalizer $localizer;

    /**
     * @param  string[]  $locales  Idiomas elegidos (al menos uno).
     * @param  string[]  $fields  Campos elegidos (los desconocidos se ignoran).
     */
    public function __construct(ExportRegistry $registry, protected string $key, array $locales, array $fields)
    {
        $this->modelClass = $registry->modelFor($key);
        $catalog = array_keys($this->modelClass::exportFields());
        // En el orden del catálogo, no en el que llegaron.
        $this->fields = array_values(array_intersect($catalog, $fields));
        $this->localizer = new ExportLocalizer(array_values(array_unique($locales)));
    }

    /** @return string[] */
    public function fields(): array
    {
        return $this->fields;
    }

    public function export(): array
    {
        $items = $this->modelClass::exportQuery()->get()
            ->map(fn (Model&ExportableContract $model) => $this->item($model))
            ->values();

        return [
            'model' => $this->key,
            'exported_at' => now()->toIso8601String(),
            'locales' => $this->localizer->locales(),
            'fields' => $this->fields,
            'count' => $items->count(),
            'items' => $items->all(),
        ];
    }

    /** Solo los campos elegidos; las Closure se evalúan solo si se eligen. */
    protected function item(Model&ExportableContract $model): array
    {
        $values = $model->exportItem($this->localizer);
        $out = [];
        foreach ($this->fields as $field) {
            if (! array_key_exists($field, $values)) {
                continue;
            }
            $value = $values[$field];
            $out[$field] = $value instanceof Closure ? $value() : $value;
        }

        return $out;
    }
}
