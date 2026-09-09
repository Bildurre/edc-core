<?php

namespace Edc\Core\Export;

use Illuminate\Database\Eloquent\Model;

/**
 * Idiomas elegidos para una exportación y cómo salen los textos: con UN
 * idioma, cadenas; con varios, mapas {es: …, en: …}. Los modelos lo usan en
 * exportItem() para los campos traducibles propios y de sus relaciones.
 */
class ExportLocalizer
{
    /** @param string[] $locales */
    public function __construct(protected array $locales) {}

    /** @return string[] */
    public function locales(): array
    {
        return $this->locales;
    }

    /** Campo traducible (spatie/translatable) en los idiomas elegidos. */
    public function tr(Model $model, string $field): string|array|null
    {
        return $this->perLocale(fn (string $locale) => $model->getTranslation($field, $locale, false));
    }

    /**
     * Un valor por idioma elegido, calculado por el callable (p. ej. un
     * nombre con género): cadena con un idioma, mapa con varios. Los textos
     * vacíos salen como null.
     */
    public function perLocale(callable $value): string|array|null
    {
        if (count($this->locales) === 1) {
            $text = $value($this->locales[0]);

            return $text === '' ? null : $text;
        }

        $out = [];
        foreach ($this->locales as $locale) {
            $text = $value($locale);
            $out[$locale] = $text === '' ? null : $text;
        }

        return $out;
    }
}
