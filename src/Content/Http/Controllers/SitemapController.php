<?php

namespace Bgm\Core\Content\Http\Controllers;

use Bgm\Core\Content\SitemapRegistry;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Sitemap de la web pública (doc 10, DC-18): una <url> por locale y entrada
 * (páginas del CRM + lo que registre el juego en el SitemapRegistry), con
 * alternates hreflang cruzados para que los buscadores casen los idiomas.
 */
class SitemapController extends Controller
{
    public function __invoke(SitemapRegistry $registry): Response
    {
        $base = rtrim(config('motor.frontend.app_url', config('app.url')), '/');

        $urls = [];
        foreach ($registry->entries() as $entry) {
            $slugs = $entry['slugs'] ?? [];
            $alternates = [];
            foreach ($slugs as $locale => $path) {
                $alternates[$locale] = $base.'/'.trim($locale.'/'.$path, '/');
            }
            foreach ($alternates as $locale => $loc) {
                $urls[] = [
                    'loc' => $loc,
                    'lastmod' => $entry['updated_at'] ?? null,
                    'alternates' => $alternates,
                ];
            }
        }

        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
        foreach ($urls as $url) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>'.e($url['loc']).'</loc>';
            foreach ($url['alternates'] as $locale => $href) {
                $xml[] = '    <xhtml:link rel="alternate" hreflang="'.e($locale).'" href="'.e($href).'"/>';
            }
            if ($url['lastmod']) {
                $xml[] = '    <lastmod>'.e($url['lastmod']).'</lastmod>';
            }
            $xml[] = '  </url>';
        }
        $xml[] = '</urlset>';

        return response(implode("\n", $xml), 200, ['Content-Type' => 'application/xml']);
    }
}
