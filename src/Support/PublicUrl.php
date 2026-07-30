<?php

namespace Edc\Core\Support;

/**
 * URLs públicas de ficheros (media, previews PNG…) SIEMPRE sobre el host de
 * la petición. Los discos construyen sus URLs con APP_URL, que puede no
 * coincidir con el host/puerto real por el que llega la petición (p. ej.
 * servir en :8010 con APP_URL en :8000): se conserva solo la RUTA y url()
 * la reconstruye sobre la petición actual. En CLI (PDF/preview/colas) no
 * hay petición y url() recae en APP_URL, que es lo correcto allí.
 */
class PublicUrl
{
    /** Reconstruye una URL absoluta sobre el host de la petición actual. */
    public static function onRequestHost(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return url((string) parse_url($url, PHP_URL_PATH));
    }
}
