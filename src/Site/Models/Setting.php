<?php

namespace Edc\Core\Site\Models;

use Illuminate\Database\Eloquent\Model;

/** Ajuste del motor: un JSON por clave (p. ej. 'site'). */
class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];
}
