<?php

namespace Bgm\Core\Pdf\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ítem de la colección temporal "para imprimir" de un usuario (doc 02):
 * una entidad renderizable (clave del PreviewRegistry + id) con sus copias.
 */
class PdfCollectionItem extends Model
{
    protected $table = 'pdf_collection_items';

    protected $fillable = ['user_id', 'entity', 'entity_id', 'copies'];

    protected function casts(): array
    {
        return ['copies' => 'integer'];
    }
}
