<?php

namespace Edc\Core\Menu\Http\Controllers;

use Edc\Core\Menu\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * Menú público (doc 10 ampliado): sincroniza (páginas/rutas nuevas u
 * huérfanas) y devuelve solo lo visible — páginas además publicadas. La
 * jerarquía sale de `pages.parent_id`: una página con hijas visibles y
 * publicadas se sirve con ellas anidadas en `children`. Cacheado
 * (motor.content.cache_ttl); se invalida en los mismos puntos que
 * motor.pages.nav y en cada escritura del menú.
 */
class PublicMenuController extends Controller
{
    public function __construct(protected MenuService $service) {}

    public function index(): JsonResponse
    {
        $this->service->sync();

        $ttl = (int) config('motor.content.cache_ttl', 300);
        $tree = Cache::remember('motor.menu.nav', $ttl, fn () => $this->service->publicTree());

        return response()->json(['data' => $tree]);
    }
}
