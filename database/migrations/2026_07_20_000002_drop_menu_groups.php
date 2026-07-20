<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limpieza para quien migró el menú de 0.4.24 (que traía GRUPOS con label
 * traducible): el rediseño los elimina — "si quiero un grupo, hago una
 * página". En BBDD frescas la 000001 ya crea el esquema final y esto no
 * hace nada (guardas por columna); en las que ejecutaron la versión vieja,
 * suelta la columna `label` y borra las filas de tipo 'group' (sus hijos
 * pasan a raíz solos: la FK de parent_id es nullOnDelete).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('menu_items', 'label')) {
            return;
        }

        DB::table('menu_items')->where('type', 'group')->delete();

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }

    public function down(): void
    {
        // Nada que restaurar: los grupos ya no existen en el modelo.
    }
};
