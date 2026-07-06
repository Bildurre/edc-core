<?php

namespace Edc\Core\Previews\Http\Controllers;

use Edc\Core\Previews\PreviewRegistry;
use Edc\Core\Previews\RenderToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Datos para la ruta /_render de la SPA (DC-04): solo con el token de
 * servicio de vida corta que emitió el backend al lanzar Browsershot.
 */
class RenderDataController extends Controller
{
    public function show(
        Request $request,
        PreviewRegistry $registry,
        RenderToken $tokens,
        string $entity,
        int $id,
    ): JsonResponse {
        abort_unless($registry->has($entity), 404);
        abort_unless($tokens->validate($request->query('token'), $entity, $id), 403);

        $model = $registry->modelFor($entity)::query()->findOrFail($id);
        $locale = app()->getLocale();

        return response()->json([
            'entity' => $entity,
            'locale' => $locale,
            'size' => $model->previewSize($entity),
            'data' => $model->renderData($locale, $entity),
        ]);
    }
}
