<?php

namespace Edc\Core\Support;

/**
 * URLs públicas de ficheros (media, previews PNG…) SIEMPRE sobre el host de
 * la petición. Los discos construyen sus URLs con APP_URL, que puede no
 * coincidir con el host/puerto real por el que llega la petición (p. ej.
 * servir en :8010 con APP_URL en :8000): se conserva solo la RUTA y url()
 * la reconstruye sobre la petición actual. En CLI (PDF/preview/colas) no
 * hay petición y url() recae en APP_URL, que es lo correcto allí.
 *
 * Y al revés, al GUARDAR contenido (texto rico, ajustes de bloques y del
 * sitio): las URL absolutas que apunten al propio motor se guardan
 * RELATIVAS a la raíz (`/storage/...`), para que el contenido no quede
 * atado al dominio donde se escribió (importar la BD de local a producción
 * dejaba imágenes e iconos en http://localhost:8010/...). En la web, la
 * SPA comparte dominio con la API en producción y en desarrollo el
 * servidor de Vite reenvía /storage a la API, así que una ruta relativa
 * carga en los dos sitios.
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

    /**
     * Si la URL es absoluta y apunta al propio motor (el origen de APP_URL
     * o el de la petición actual), la devuelve relativa a la raíz (ruta +
     * query + ancla). Cualquier otra cosa se devuelve tal cual.
     */
    public static function relativize(?string $url): ?string
    {
        if ($url === null || ! preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return $url;
        }

        $origin = strtolower($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''));
        if (! in_array($origin, self::selfOrigins(), true)) {
            return $url;
        }

        $relative = ($parts['path'] ?? '') ?: '/';
        if (isset($parts['query'])) {
            $relative .= '?'.$parts['query'];
        }
        if (isset($parts['fragment'])) {
            $relative .= '#'.$parts['fragment'];
        }

        return $relative;
    }

    /** Aplica relativize() a todos los strings de un array, anidados incluidos. */
    public static function relativizeDeep(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::relativize($value);
        }
        if (is_array($value)) {
            return array_map(fn ($item) => self::relativizeDeep($item), $value);
        }

        return $value;
    }

    /**
     * Orígenes «propios»: el de APP_URL y el de la petición en curso (en
     * desarrollo la API puede servirse en un puerto distinto al de APP_URL).
     *
     * @return string[]
     */
    protected static function selfOrigins(): array
    {
        $origins = [];
        foreach ([config('app.url'), app()->bound('request') ? request()->getSchemeAndHttpHost() : null] as $candidate) {
            $candidate = strtolower(rtrim((string) $candidate, '/'));
            if ($candidate !== '' && preg_match('#^https?://#', $candidate)) {
                $origins[] = $candidate;
            }
        }

        return array_values(array_unique($origins));
    }
}
