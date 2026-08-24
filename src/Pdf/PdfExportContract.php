<?php

namespace Edc\Core\Pdf;

use Illuminate\Database\Eloquent\Model;

/**
 * Un "export" de PDF que define el juego (doc 02): qué entidad es la dueña
 * (o ninguna, si es global), qué ítems van dentro y con qué plantilla/layout.
 * El motor pone el pipeline entero (cola, ensamblado, almacenamiento,
 * regeneración, limpieza); el juego solo describe el contenido.
 */
interface PdfExportContract
{
    /** Clase del modelo dueño del PDF, o null si el export es global. */
    public function sourceModel(): ?string;

    /**
     * Ítems imprimibles para (source, locale).
     *
     * @return PrintableItem[]
     */
    public function items(?Model $source, string $locale): array;

    /**
     * Entidades dueñas disponibles para el gestor del admin:
     * [['id' => int, 'label' => string], ...]. Solo aplica a exports con
     * sourceModel; los globales devuelven [].
     */
    public function sources(string $locale): array;

    /** Clave del layout (motor.pdf.layouts) que usa este export. */
    public function layout(): string;

    /** Nombre de fichero (sin extensión) para (source, locale). */
    public function filename(?Model $source, string $locale): string;

    /**
     * Nombre LEGIBLE del PDF en el idioma pedido: el que ve el visitante en
     * la card de Descargas y el que titula la pestaña del navegador
     * (Content-Disposition). No es el filename de la BD (ese sigue siendo un
     * slug): para exports con entidad dueña sale de su nombre/título
     * traducible; para los globales, de las etiquetas por locale que declare
     * el export; y si no hay nada, del filename embellecido.
     */
    public function displayName(?Model $source, string $locale): string;

    /**
     * Vista Blade del PDF. null = la rejilla genérica del motor
     * ('motor::pdf.grid'). Un juego puede apuntar a una vista propia para
     * layouts especiales (portadas, reglas, etc.).
     */
    public function view(): ?string;
}
