<?php

namespace Bgm\Core\Previews;

/**
 * Contrato que implementa cada entidad del juego renderizable a PNG (doc 01).
 * El motor no conoce las entidades: el juego las registra en el
 * PreviewRegistry y declara aquí su tamaño, sus disparadores y sus datos.
 *
 * Un mismo modelo puede registrarse bajo VARIAS claves (una preview por
 * clave): la por defecto y otras especiales (p. ej. 'house' y
 * 'house-counter'). Los métodos reciben la clave ($type) para poder variar
 * tamaño o datos por preview; null = la primera registrada.
 */
interface PreviewableContract
{
    /** Tamaño del componente visual en píxeles CSS: ['width' => int, 'height' => int]. */
    public function previewSize(?string $type = null): array;

    /** Etiqueta legible de la entidad para los listados del gestor de previews. */
    public function previewLabel(string $locale): string;

    /**
     * Campos cuyo cambio invalida la preview (declarativo). Los cambios en
     * otros campos (p. ej. is_published) no regeneran nada.
     */
    public function previewTriggerFields(): array;

    /**
     * Payload que consumirá el componente Vue de la ruta /_render para el
     * locale dado. Única fuente de datos de la captura (DC-04).
     */
    public function renderData(string $locale, ?string $type = null): array;
}
