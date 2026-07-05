<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDF generados (doc 02). Polimórfico: cualquier entidad del juego puede ser
 * dueña (source), o ninguna (exports globales y colecciones temporales).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_pdfs', function (Blueprint $table) {
            $table->id();
            // Tipo de export (clave del PdfExportRegistry) o 'collection'
            // para los temporales a la carta.
            $table->string('type');
            $table->nullableMorphs('source');
            // Dueño de los temporales: usuario logueado O token de invitado
            // (uuid que la SPA genera y guarda en localStorage, doc 02).
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_token', 64)->nullable()->index();
            $table->string('locale', 12);
            $table->string('layout');
            $table->string('path')->nullable();
            $table->string('filename');
            $table->string('status')->default('pending'); // pending | ready | failed
            $table->text('error')->nullable();
            // Snapshot de ítems para los temporales: [{entity, id, copies}].
            $table->json('payload')->nullable();
            $table->boolean('is_permanent')->default(true);
            $table->datetime('expires_at')->nullable();
            $table->datetime('generated_at')->nullable();
            $table->datetimes();

            $table->index(['type', 'source_type', 'source_id', 'locale']);
        });

        // Colección temporal "para imprimir" (doc 02): ítems elegidos a la
        // carta con copias; de aquí sale el PDF temporal. Es de un usuario
        // logueado O de un invitado (token de la SPA), como en CDL.
        Schema::create('pdf_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('guest_token', 64)->nullable()->index();
            // Clave del PreviewRegistry (character, scheme, ...) + id.
            $table->string('entity');
            $table->unsignedBigInteger('entity_id');
            $table->unsignedInteger('copies')->default(1);
            $table->datetimes();

            $table->unique(['user_id', 'entity', 'entity_id']);
            $table->unique(['guest_token', 'entity', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_collection_items');
        Schema::dropIfExists('generated_pdfs');
    }
};
