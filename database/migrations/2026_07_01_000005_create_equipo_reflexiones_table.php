<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipo_reflexiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->enum('tipo', ['individual', 'grupal']);
            $table->string('autor_nombre')->nullable();   // null en grupal, nombre en individual
            $table->json('respuestas');                   // [{pregunta: "...", respuesta: "..."}]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipo_reflexiones');
    }
};
