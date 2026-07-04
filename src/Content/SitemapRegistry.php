<?php

namespace Bgm\Core\Content;

/**
 * Registro de URLs del sitemap (doc 10, DC-18). El motor aporta las páginas
 * publicadas del CRM; cada juego añade providers para sus entidades públicas
 * (facade Sitemap) en el boot de su AppServiceProvider:
 *
 *   Sitemap::add(fn () => Character::published()->get()
 *       ->map(fn ($c) => [
 *           'slugs' => collect($c->getTranslations('slug'))
 *               ->map(fn ($slug) => "personajes/{$slug}")->all(),
 *           'updated_at' => $c->updated_at?->toDateString(),
 *       ])->all());
 *
 * Cada entrada: ['slugs' => [locale => ruta-sin-prefijo], 'updated_at' => ?].
 * La URL final es {app_url}/{locale}/{ruta} (la home usa ruta '').
 */
class SitemapRegistry
{
    /** @var array<callable(): array> */
    protected array $providers = [];

    public function add(callable $provider): void
    {
        $this->providers[] = $provider;
    }

    /** Todas las entradas, evaluando los providers en orden de registro. */
    public function entries(): array
    {
        $entries = [];
        foreach ($this->providers as $provider) {
            $entries = array_merge($entries, $provider());
        }

        return $entries;
    }
}
