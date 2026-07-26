<?php

namespace Edc\Core\Support;

/**
 * Plegado de texto para buscar y ordenar "a lo humano": minúsculas y sin
 * acentos ("CaMiON" encuentra "camión"). Los valores JSON traducibles
 * comparan en BINARIO (collation utf8mb4_bin en MySQL) y el lower() de
 * SQLite solo baja ASCII, así que el plegado se construye explícito en SQL
 * (lower + cadena de replace) y se aplica a la COLUMNA y al TÉRMINO por
 * igual. El alfabeto cubierto es el del juego (es/en/eu): vocales
 * acentuadas, diéresis y eñe.
 */
final class SqlFold
{
    /** Pares acentuada => plana, en minúscula Y mayúscula (el lower() de
     *  SQLite no baja 'Á': hay que plegar las dos formas). */
    private const MAP = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ü' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
        'Ü' => 'u', 'Ñ' => 'n',
    ];

    /** Expresión SQL que pliega una columna YA envuelta por el grammar. */
    public static function expression(string $wrappedColumn): string
    {
        $sql = "lower({$wrappedColumn})";
        foreach (self::MAP as $from => $to) {
            $sql = "replace({$sql}, '{$from}', '{$to}')";
        }

        return $sql;
    }

    /** El mismo plegado, aplicado en PHP al término de búsqueda. */
    public static function term(string $value): string
    {
        return strtr(mb_strtolower($value), self::MAP);
    }
}
