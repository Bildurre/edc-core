<?php

namespace Bgm\Core\Content;

use Bgm\Core\Content\Models\Block;
use Bgm\Core\Content\Models\Page;
use Illuminate\Support\Facades\Cache;

/**
 * Payload público de una página (doc 03): metadatos + bloques con sus
 * settings localizados y sus datos resueltos (resolveData del BlockType).
 * Cacheado por (página, locale) — DC-10: PageService lo invalida al cambiar
 * la página o sus bloques; el TTL corto cubre los cambios en las entidades
 * que consultan los bloques-con-datos.
 */
class PageRenderer
{
    public function __construct(protected BlockTypeRegistry $registry) {}

    public function render(Page $page, string $locale): array
    {
        $ttl = (int) config('motor.content.cache_ttl', 300);

        return Cache::remember(
            "motor.page.{$page->id}.{$locale}",
            $ttl,
            fn () => $this->build($page, $locale),
        );
    }

    protected function build(Page $page, string $locale): array
    {
        $blocks = $page->blocks()->get()
            ->filter(fn (Block $block) => $this->registry->has($block->type))
            ->map(function (Block $block) use ($locale) {
                $type = $this->registry->get($block->type);

                return [
                    'id' => $block->id,
                    'type' => $block->type,
                    'component' => $type->component(),
                    'settings' => $type->localizeSettings($block->settings, $locale),
                    'data' => $type->resolveData($block, $locale),
                    'is_printable' => $block->is_printable,
                    'is_indexable' => $block->is_indexable,
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $page->id,
            'title' => $page->getTranslation('title', $locale),
            'template' => $page->template,
            'background_image' => $page->background_image,
            'is_home' => $page->is_home,
            'is_printable' => $page->is_printable,
            'meta' => [
                'title' => $page->getTranslation('meta_title', $locale)
                    ?: $page->getTranslation('title', $locale),
                'description' => $page->getTranslation('meta_description', $locale)
                    ?: str(strip_tags((string) $page->getTranslation('description', $locale)))->limit(160)->toString(),
            ],
            // Slug por locale: la SPA construye el selector de idioma y las
            // redirecciones a la URL canónica (DC-12).
            'slugs' => $page->getTranslations('slug'),
            'blocks' => $blocks,
        ];
    }
}
