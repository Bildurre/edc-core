<?php

namespace Bgm\Core\Icons\Http\Controllers;

use Bgm\Core\Icons\Http\Resources\IconResource;
use Bgm\Core\Icons\Models\Icon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

/**
 * Biblioteca de iconos del motor: listado para el selector del editor y gestión
 * (subida/borrado) desde el admin. Cada juego sube su propio set.
 */
class IconController extends Controller
{
    /** Listado (para el selector del WYSIWYG y la gestión). */
    public function index()
    {
        return IconResource::collection(Icon::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            // Se admite SVG (la regla `image` usa getimagesize y lo rechaza).
            'image' => ['required', 'file', 'mimes:svg,png,jpg,jpeg,webp,gif', 'max:2048'],
        ])->validate();

        $icon = new Icon;
        $icon->name = $data['name'];
        $icon->save();
        $icon->setImageFromRequest($request);

        return (new IconResource($icon))->response()->setStatusCode(201);
    }

    /**
     * Edición: renombrar y, opcionalmente, sustituir la imagen. Llega por
     * POST (multipart no viaja en PUT nativo de PHP).
     */
    public function update(Request $request, Icon $icon)
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'file', 'mimes:svg,png,jpg,jpeg,webp,gif', 'max:2048'],
        ])->validate();

        $icon->name = $data['name'];
        $icon->save();

        if ($request->hasFile('image')) {
            $icon->setImageFromRequest($request);
        }

        return new IconResource($icon->refresh());
    }

    public function destroy(Icon $icon)
    {
        $icon->clearMediaCollection('image');
        $icon->delete();

        return response()->noContent();
    }
}
