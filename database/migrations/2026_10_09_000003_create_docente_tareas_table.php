<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tareas personales del docente (lista "Tareas pendientes" del panel docente y del
// Calendario). Antes vivían en localStorage ('docente_notas'): se perdían al cambiar
// de navegador. El frontend las sube aquí la primera vez y limpia esa clave.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_tareas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('texto', 200);
            $table->boolean('hecha')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'hecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_tareas');
    }
};
