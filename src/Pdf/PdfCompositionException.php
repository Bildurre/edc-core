<?php

namespace Edc\Core\Pdf;

use RuntimeException;

/**
 * Error DELIBERADO al componer un PDF (p. ej. sin ítems imprimibles): su
 * mensaje es apto para el frontend. Cualquier otra excepción se guarda como
 * error genérico y el detalle se queda en los logs (no se filtra a la UI).
 */
class PdfCompositionException extends RuntimeException {}
