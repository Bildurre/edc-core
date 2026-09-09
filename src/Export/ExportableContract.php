<?php

namespace Edc\Core\Export;

use Illuminate\Database\Eloquent\Builder;

/**
 * Modelo EXPORTABLE a JSON desde el admin (sección Exportación, solo
 * administradores). El juego marca sus modelos implementando este contrato
 * y los registra en el boot de su AppServiceProvider:
 *
 *     Exports::register('heroes', Hero::class);
 *
 * El JSON es para LEER (no para reimportar): las relaciones salen
 * DESPLEGADAS con su nombre y sus datos relevantes en vez de su id, de modo
 * que el fichero se lea de seguido. El administrador elige los idiomas y
 * los campos; el exportador recorre exportQuery() y, por cada elemento,
 * pide exportItem() y se queda con los campos elegidos.
 */
interface ExportableContract
{
    /**
     * Catálogo de campos exportables, en el orden en que saldrán:
     * clave => grupo. Grupos sugeridos: 'basic', 'texts', 'relations' (el
     * admin los etiqueta por convención export.groups.<grupo>).
     *
     * @return array<string, string>
     */
    public static function exportFields(): array;

    /**
     * Consulta base de la exportación: eager loading de lo que exportItem()
     * necesita y el orden de salida.
     */
    public static function exportQuery(): Builder;

    /**
     * Valor de cada campo del catálogo para este elemento: clave => valor o
     * Closure (perezosa: solo se evalúa si el campo se ha elegido). Los
     * textos traducibles se resuelven con el localizador ($l->tr($modelo,
     * $campo)): cadena con un idioma elegido, mapa por idioma con varios.
     *
     * @return array<string, mixed>
     */
    public function exportItem(ExportLocalizer $l): array;
}
