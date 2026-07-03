<?php

namespace Bgm\Core\Content\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * Subida de imágenes del CRM (campos `image` del esquema de bloques): guarda
 * en el disco del motor y devuelve la URL pública, que es lo que se almacena
 * en `settings`. La limpieza de huérfanos llegará con el backup (doc 06).
 */
class ContentUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $disk = config('motor.storage.disk', 'public');
        $path = $request->file('image')->store('content', $disk);

        return response()->json([
            'url' => Storage::disk($disk)->url($path),
            'path' => $path,
        ], 201);
    }
}
