<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actualización para instalaciones existentes: la colección "para imprimir"
 * admite INVITADOS (doc 02) — guest_token en generated_pdfs y
 * pdf_collection_items, y user_id pasa a nullable. Las creates consolidadas
 * ya lo traen para instalaciones nuevas: todo va con guardas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('generated_pdfs', 'guest_token')) {
            Schema::table('generated_pdfs', function (Blueprint $table) {
                $table->string('guest_token', 64)->nullable()->index();
            });
        }

        if (! Schema::hasColumn('pdf_collection_items', 'guest_token')) {
            Schema::table('pdf_collection_items', function (Blueprint $table) {
                $table->string('guest_token', 64)->nullable()->index();
                $table->unique(['guest_token', 'entity', 'entity_id']);
            });
            Schema::table('pdf_collection_items', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás: las columnas con guardas no molestan.
    }
};
