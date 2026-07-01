<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biblioteca de iconos del juego (DC-15/DC-24). Cada juego sube su set de
 * iconos (dados, recursos, símbolos…) y los inserta en el texto enriquecido.
 * La imagen vive en Spatie MediaLibrary (colección 'image').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icons');
    }
};
