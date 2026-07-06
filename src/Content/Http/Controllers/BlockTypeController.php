<?php

namespace Edc\Core\Content\Http\Controllers;

use Edc\Core\Content\BlockTypeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Paleta de tipos de bloque (doc 03): los registrados (motor + juego) con su
 * esquema de campos serializado — el BlockEditor del admin se genera de aquí.
 */
class BlockTypeController extends Controller
{
    public function index(BlockTypeRegistry $registry): JsonResponse
    {
        return response()->json(['data' => $registry->toArray()]);
    }
}
