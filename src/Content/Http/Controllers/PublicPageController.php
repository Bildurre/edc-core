<?php

namespace Bgm\Core\Content\Http\Controllers;

use Bgm\Core\Content\Models\Page;
use Bgm\Core\Content\PageRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * Render público de páginas (doc 03): navegación (páginas raíz publicadas),
 * home y página por slug traducible. El slug se resuelve en CUALQUIER locale
 * (ResolvesBySlug): el payload trae los slugs por locale y la SPA redirige a
 * la canónica del idioma activo (DC-12).
 */
class PublicPageController extends Controller
{
    public function __construct(protected PageRenderer $renderer) {}

    /**
     * Menú público: páginas raíz publicadas con título y slug por locale, y
     * sus hijas publicadas (el nav las despliega como submenú, patrón CDL).
     */
    public function nav(): JsonResponse
    {
        $ttl = (int) config('motor.content.cache_ttl', 300);

        $items = Cache::remember('motor.pages.nav', $ttl, function () {
            return Page::query()
                ->published()
                ->root()
                ->orderBy('order')
                ->with(['children' => fn ($q) => $q->published()->orderBy('order')])
                ->get()
                ->map(fn (Page $page) => [
                    'id' => $page->id,
                    'title' => $page->getTranslations('title'),
                    'slugs' => $page->getTranslations('slug'),
                    'is_home' => $page->is_home,
                    'children' => $page->children->map(fn (Page $child) => [
                        'id' => $child->id,
                        'title' => $child->getTranslations('title'),
                        'slugs' => $child->getTranslations('slug'),
                    ])->all(),
                ])
                ->all();
        });

        return response()->json(['data' => $items]);
    }

    /** La página marcada como home (o 404 si no hay). */
    public function home(): JsonResponse
    {
        $page = Page::query()->published()->where('is_home', true)->firstOrFail();

        return response()->json(['data' => $this->renderer->render($page, app()->getLocale())]);
    }

    /** Página publicada por slug (en cualquier locale). */
    public function show(string $slug): JsonResponse
    {
        $page = Page::query()->published()->whereSlug($slug)->firstOrFail();

        return response()->json(['data' => $this->renderer->render($page, app()->getLocale())]);
    }
}
