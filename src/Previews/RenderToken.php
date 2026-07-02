<?php

namespace Bgm\Core\Previews;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Token de servicio de vida corta (DC-04): el backend lo emite al lanzar
 * Browsershot y la ruta /_render lo devuelve para pedir los datos a la API.
 * La ruta de datos no es accesible sin él.
 */
class RenderToken
{
    protected function cacheKey(string $token): string
    {
        return "motor.render-token.{$token}";
    }

    /** Emite un token válido solo para esa entidad+id. */
    public function issue(string $entityKey, int|string $id): string
    {
        $token = Str::random(40);

        Cache::put(
            $this->cacheKey($token),
            ['entity' => $entityKey, 'id' => (string) $id],
            now()->addSeconds((int) config('motor.previews.token_ttl', 300)),
        );

        return $token;
    }

    /** ¿El token autoriza a leer los datos de esa entidad+id? */
    public function validate(?string $token, string $entityKey, int|string $id): bool
    {
        if (! $token) {
            return false;
        }

        $payload = Cache::get($this->cacheKey($token));

        return is_array($payload)
            && hash_equals($payload['entity'], $entityKey)
            && hash_equals($payload['id'], (string) $id);
    }
}
