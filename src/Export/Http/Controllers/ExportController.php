<?php

namespace Edc\Core\Export\Http\Controllers;

use Edc\Core\Export\ExportRegistry;
use Edc\Core\Export\JsonExporter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Exportación a JSON de los modelos registrados como exportables (solo
 * administradores: gate `export-data`): el catálogo para el formulario del
 * admin y la descarga del JSON con lo elegido.
 */
class ExportController extends Controller
{
    public function __construct(protected ExportRegistry $registry) {}

    /** Modelos con sus campos (y grupo) e idiomas del sitio. */
    public function options()
    {
        $models = [];
        foreach ($this->registry->all() as $key => $modelClass) {
            $models[] = [
                'key' => $key,
                'fields' => collect($modelClass::exportFields())
                    ->map(fn (string $group, string $field) => ['key' => $field, 'group' => $group])
                    ->values()
                    ->all(),
            ];
        }

        return response()->json([
            'data' => [
                'models' => $models,
                'locales' => collect(config('motor.locales', []))
                    ->map(fn (array $locale, string $code) => ['code' => $code, 'name' => $locale['name'] ?? $code])
                    ->values()
                    ->all(),
                'default_locale' => config('motor.default_locale'),
            ],
        ]);
    }

    /** El JSON, como descarga (<modelo>-<fecha>.json). */
    public function export(Request $request, string $model)
    {
        abort_unless($this->registry->has($model), 404);

        $data = Validator::make($request->all(), [
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => ['string', Rule::in(array_keys(config('motor.locales', [])))],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string', Rule::in(array_keys($this->registry->modelFor($model)::exportFields()))],
        ])->validate();

        $exporter = new JsonExporter($this->registry, $model, $data['locales'], $data['fields']);
        $filename = sprintf('%s-%s.json', $model, now()->format('Y-m-d'));

        return response()->json($exporter->export(), 200, [
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
