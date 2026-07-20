<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menú configurable de la web pública (doc 10 ampliado): mezcla páginas del
 * CRM y "rutas" propias del juego bajo un árbol de UN nivel (los grupos son
 * del admin; una página o ruta puede colgar de un grupo, un grupo no puede
 * colgar de otro). MenuSync mantiene sincronizados los items de página/ruta;
 * los grupos los gestiona el admin a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_visible')->default(true);
            // 'page' | 'route' | 'group'.
            $table->string('type');
            $table->foreignId('page_id')->nullable()->constrained('pages')->cascadeOnDelete();
            // Clave declarada por el juego en motor.menu.routes (índices de
            // entidades, descargas…); solo para type='route'.
            $table->string('route_key')->nullable();
            // Solo los grupos tienen label propia (traducible); páginas y
            // rutas toman su etiqueta de la página o de routeLabels.
            $table->json('label')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
