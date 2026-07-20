<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menú configurable de la web pública (doc 10 ampliado): mezcla páginas del
 * CRM y "rutas" propias del juego. Sin grupos — "si quiero un grupo, hago
 * una página" (una página con hijas actúa de desplegable). La jerarquía es
 * SIEMPRE la del CRM (`pages.parent_id`, un solo nivel): por eso `parent_id`
 * de esta tabla queda solo para anidar una RUTA bajo una página (las páginas
 * nunca lo usan, su padre se lee de `pages`). MenuSync mantiene un item por
 * cada página no-home y por cada route_key declarada por el juego.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            // Solo para type='route' (anidarla bajo una página raíz); las
            // páginas siempre van null aquí, su jerarquía es pages.parent_id.
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->nullOnDelete();
            // Orden del menú en su nivel (raíz, o hijas de la misma página).
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_visible')->default(true);
            // 'page' | 'route'.
            $table->string('type');
            $table->foreignId('page_id')->nullable()->constrained('pages')->cascadeOnDelete();
            // Clave declarada por el juego en motor.menu.routes (índices de
            // entidades, descargas…); solo para type='route'.
            $table->string('route_key')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
