<?php

namespace Bgm\Core\Previews\Http\Controllers;

use Bgm\Core\Previews\PreviewRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Previews desde el admin: consultar las URLs generadas y regenerar en cola
 * con un clic (el gestor completo del admin-kit llegará con el PDF, doc 02).
 */
class PreviewController extends Controller
{
    public function show(PreviewRegistry $registry, string $entity, int $id): JsonResponse
    {
        abort_unless($registry->has($entity), 404);
        $model = $registry->modelFor($entity)::query()->findOrFail($id);

        return response()->json(['data' => $model->previewUrls()]);
    }

    public function regenerate(
        Request $request,
        PreviewRegistry $registry,
        string $entity,
        int $id,
    ): JsonResponse {
        abort_unless($registry->has($entity), 404);
        $model = $registry->modelFor($entity)::query()->findOrFail($id);

        $locales = $request->filled('locale')
            ? [$request->string('locale')->toString()]
            : null;

        $model->regeneratePreviews($locales);

        return response()->json(['message' => __('motor::motor.previews_queued')], 202);
    }
}
