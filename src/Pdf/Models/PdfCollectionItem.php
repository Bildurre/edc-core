<?php

namespace Bgm\Core\Pdf\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ítem de la colección temporal "para imprimir" (doc 02): una entidad
 * renderizable (clave del PreviewRegistry + id) con sus copias. El dueño es
 * un usuario logueado o un token de invitado (como en CDL).
 */
class PdfCollectionItem extends Model
{
    protected $table = 'pdf_collection_items';

    protected $fillable = ['user_id', 'guest_token', 'entity', 'entity_id', 'copies'];

    protected function casts(): array
    {
        return ['copies' => 'integer'];
    }
}
