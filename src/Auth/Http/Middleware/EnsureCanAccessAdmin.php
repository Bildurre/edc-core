<?php

namespace Edc\Core\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe el acceso a las rutas del panel de administración a los roles
 * configurados en motor.auth.admin_roles (por defecto admin y editor).
 */
class EnsureCanAccessAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(config('motor.auth.admin_roles', ['admin', 'editor']))) {
            abort(403, __('motor::motor.admin_forbidden'));
        }

        return $next($request);
    }
}
