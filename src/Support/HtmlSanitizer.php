<?php

namespace Edc\Core\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitizador de HTML por lista blanca (DC-09): lo que produce TipTap y nada
 * más. Se aplica en servidor al guardar texto rico (bloques del CRM y campos
 * richtext de las entidades del juego). Sin dependencias externas.
 */
class HtmlSanitizer
{
    /** Etiquetas permitidas => atributos permitidos. */
    protected const ALLOWED = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        's' => [],
        'u' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'ul' => [],
        'ol' => ['start'],
        'li' => [],
        'blockquote' => [],
        'a' => ['href', 'target', 'rel'],
        // Iconos del juego insertados por el RichTextInput (<img class="rt-icon">).
        'img' => ['src', 'alt', 'class'],
        'span' => ['class'],
    ];

    /** Esquemas de URL admitidos en href/src. */
    protected const SCHEMES = ['http', 'https', 'mailto'];

    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        $doc = new DOMDocument;
        // Envuelto en un contenedor propio para extraer solo el fragmento.
        $ok = @$doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="__root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NONET,
        );

        if (! $ok) {
            return strip_tags($html);
        }

        $root = $doc->getElementById('__root');
        if ($root === null) {
            return strip_tags($html);
        }

        $this->cleanChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    protected function cleanChildren(DOMNode $node): void
    {
        // Copia de la lista: se muta al eliminar/desenvolver nodos.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue; // texto y demás nodos, tal cual
            }

            $tag = strtolower($child->nodeName);

            if (! array_key_exists($tag, self::ALLOWED)) {
                // Etiqueta no permitida: se desenvuelve (se conservan los hijos).
                $this->cleanChildren($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            // Atributos: solo los de la lista, y URLs con esquema seguro.
            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                if (! in_array($name, self::ALLOWED[$tag], true)) {
                    $child->removeAttribute($attribute->name);

                    continue;
                }
                if (in_array($name, ['href', 'src'], true) && ! $this->safeUrl($attribute->value)) {
                    $child->removeAttribute($attribute->name);
                }
            }

            $this->cleanChildren($child);
        }
    }

    protected function safeUrl(string $url): bool
    {
        $url = trim($url);

        // Relativas y anclas: bien.
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === '' || in_array($scheme, self::SCHEMES, true);
    }
}
