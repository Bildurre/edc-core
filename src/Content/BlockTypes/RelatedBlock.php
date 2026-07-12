<?php

namespace Edc\Core\Content\BlockTypes;

use Edc\Core\Content\BlockType;
use Edc\Core\Content\Fields\Field;
use Edc\Core\Content\Models\Block;
use Edc\Core\Previews\CatalogItem;
use Edc\Core\Previews\PreviewRegistry;

/**
 * Rejilla de entidades relacionadas: cualquier entidad del registry de
 * previews, las N más recientes o al azar, con botón opcional al índice.
 * Único bloque 'data' del motor: no sabe qué es cada entidad, solo pinta
 * ítems de catálogo (CatalogItem); los enlaces los resuelve la app.
 *
 * Ojo: resolveData se cachea con la página (TTL motor.content.cache_ttl),
 * así que 'random' rota solo al expirar la caché — aceptado.
 */
class RelatedBlock extends BlockType
{
    public static string $key = 'related';

    public string $name = 'Relacionados';

    public string $icon = 'layout-grid';

    public string $category = 'data';

    public function fields(): array
    {
        // fields() se evalúa por petición: las opciones del select reflejan
        // el registry del juego en tiempo real (admin y validación).
        $keys = app(PreviewRegistry::class)->keys();

        return [
            Field::text('title')->label('Título')->translatable(),
            Field::text('subtitle')->label('Subtítulo')->translatable(),
            Field::select('preview_key', $keys)->label('Entidad'),
            Field::select('mode', [
                'latest' => 'Más recientes',
                'random' => 'Aleatorias',
            ])->label('Modo'),
            Field::number('count')->label('Número de elementos')->default(4)->min(1)->max(12),
            Field::boolean('with_button')->label('Con botón al índice'),
            Field::text('button_label')->label('Texto del botón')->translatable(),
        ];
    }

    public function resolveData(Block $block, string $locale): array
    {
        $settings = $this->localizeSettings($block->settings, $locale);
        $registry = app(PreviewRegistry::class);
        $key = $settings['preview_key'] ?? null;

        // Clave desregistrada o vacía: render vacío, sin reventar la página.
        if (! is_string($key) || ! $registry->has($key)) {
            return ['key' => null, 'items' => []];
        }

        $query = CatalogItem::query($registry->modelFor($key));

        ($settings['mode'] ?? 'latest') === 'random'
            ? $query->inRandomOrder()
            : $query->orderByDesc('id');

        $count = min(max((int) ($settings['count'] ?? 4), 1), 12);

        return [
            'key' => $key,
            'items' => $query->limit($count)->get()
                ->map(fn ($model) => CatalogItem::fromModel($model, $key, $locale))
                ->values()
                ->all(),
        ];
    }
}
