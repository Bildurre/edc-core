<?php

namespace Bgm\Core\Previews;

use Spatie\Browsershot\Browsershot;

/**
 * Única fuente de configuración de Browsershot (doc 01): chrome path,
 * noSandbox, args de producción, escala y esperas. En choque esto vivía
 * disperso entre el job y ConfiguresBrowsershot.
 */
class PreviewRenderer
{
    /** Captura una URL real de la SPA a PNG con el tamaño dado (px CSS). */
    public function capture(string $url, int $width, int $height, string $savePath): void
    {
        $shot = Browsershot::url($url)
            // Headless moderno: usa el binario 'chrome' que descarga puppeteer
            // con npm install. Sin esto, Browsershot pide 'chrome-headless-shell',
            // que puppeteer NO baja por defecto ("Could not find chrome-headless-shell").
            ->newHeadless()
            ->windowSize($width, $height)
            ->deviceScaleFactor((int) config('motor.previews.scale', 2))
            ->hideBackground() // PNG con fondo transparente si el componente no pinta fondo
            ->timeout((int) config('motor.previews.timeout', 60))
            ->waitUntilNetworkIdle()
            // La vista /_render marca window.__bgmRenderReady al terminar de
            // montar el componente con sus datos (y fuentes cargadas).
            ->waitForFunction('window.__bgmRenderReady === true')
            ->noSandbox()
            ->dismissDialogs();

        if ($chrome = config('motor.previews.chrome_path')) {
            $shot->setChromePath($chrome);
        }

        if ($node = config('motor.previews.node_binary')) {
            $shot->setNodeBinary($node);
        }

        $shot->save($savePath);
    }
}
