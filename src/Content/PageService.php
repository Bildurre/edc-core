<?php

namespace Edc\Core\Content;

use Edc\Core\Content\Models\Block;
use Edc\Core\Content\Models\Page;
use Illuminate\Support\Facades\Cache;

/**
 * CRUD y operaciones de páginas (doc 03): traducciones, jerarquía, orden,
 * home única y caché del payload público por (página, locale) — DC-10:
 * cualquier cambio en la página o sus bloques la invalida.
 */
class PageService
{
    /** Campos traducibles que llegan como {locale: valor}. */
    protected const TRANSLATABLE = ['title', 'description', 'meta_title', 'meta_description'];

    public function create(array $data): Page
    {
        $page = new Page;
        $this->apply($page, $data);
        $page->save();

        return $page;
    }

    public function update(Page $page, array $data): Page
    {
        $this->apply($page, $data);
        $page->save();
        $this->forget($page);

        return $page;
    }

    /** A la papelera: las hijas pasan a raíz (no se pierden). */
    public function delete(Page $page): void
    {
        $page->children()->update(['parent_id' => null]);
        $page->delete();
        $this->forget($page);
    }

    public function restore(int $id): Page
    {
        $page = Page::onlyTrashed()->findOrFail($id);
        $page->restore();

        return $page;
    }

    public function forceDelete(int $id): void
    {
        $page = Page::onlyTrashed()->findOrFail($id);
        $page->forceDelete(); // los bloques caen por FK cascade
    }

    /** Marca la home (solo puede haber una). */
    public function setHome(Page $page): void
    {
        Page::query()->where('is_home', true)->update(['is_home' => false]);
        $page->forceFill(['is_home' => true])->save();
        Cache::forget('motor.pages.nav');
        $this->forget($page);
    }

    /** Reordena páginas hermanas: la lista de ids marca el orden. */
    public function reorder(array $ids): void
    {
        foreach (array_values($ids) as $index => $id) {
            Page::query()->whereKey($id)->update(['order' => $index]);
        }
        Cache::forget('motor.pages.nav');
    }

    /** Invalida la caché pública de una página (todos los locales). */
    public function forget(Page|Block $model): void
    {
        $pageId = $model instanceof Page ? $model->id : $model->page_id;

        foreach (array_keys(config('motor.locales', [])) as $locale) {
            Cache::forget("motor.page.{$pageId}.{$locale}");
        }
        Cache::forget('motor.pages.nav');
    }

    protected function apply(Page $page, array $data): void
    {
        foreach (self::TRANSLATABLE as $field) {
            if (array_key_exists($field, $data)) {
                $page->replaceTranslations(
                    $field,
                    array_filter($data[$field] ?? [], fn ($v) => $v !== null && $v !== ''),
                );
            }
        }

        foreach (['parent_id', 'order', 'template', 'background_image', 'is_published', 'is_printable'] as $field) {
            if (array_key_exists($field, $data)) {
                $page->{$field} = $data[$field];
            }
        }
    }
}
