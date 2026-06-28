<?php

namespace Bgm\Core\I18n\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el locale de la petición (DC-03): prioridad ?locale > Accept-Language >
 * default. Solo acepta locales declarados en motor.locales.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = array_keys(config('motor.locales', []));

        $locale = $request->query('locale')
            ?: $request->getPreferredLanguage($available)
            ?: config('motor.default_locale');

        if (in_array($locale, $available, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
