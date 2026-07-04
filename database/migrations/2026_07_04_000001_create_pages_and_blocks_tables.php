<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM de páginas y bloques (doc 03). Las páginas son estructuradas (columnas
 * reales, traducibles en JSON); los bloques son deliberadamente simples: el
 * TIPO da la forma (esquema de campos declarativo) y TODOS sus valores viven
 * en `settings` JSON — el motor no añade columnas por tipo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->json('title');
            $table->json('description')->nullable();
            $table->json('slug');
            $table->string('template')->default('default');
            // Imagen de fondo decorativa (URL): capa fija tras el contenido,
            // atenuada según el tema (patrón CDL).
            $table->string('background_image')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_home')->default(false);
            $table->boolean('is_printable')->default(false);
            // SEO (doc 10): por locale.
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            // Reservado para bloques anidados (columnas/layout); hoy plano.
            $table->foreignId('parent_id')->nullable()->constrained('blocks')->nullOnDelete();
            $table->string('type'); // clave del BlockTypeRegistry
            $table->unsignedInteger('order')->default(0);
            // Valores del esquema de campos del tipo (traducibles incluidos).
            $table->json('settings')->nullable();
            $table->boolean('is_printable')->default(true);
            $table->boolean('is_indexable')->default(true);
            $table->timestamps();

            $table->index(['page_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('pages');
    }
};
