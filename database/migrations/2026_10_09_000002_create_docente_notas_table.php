<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Notas personales del docente (vista Calendario). `fecha` opcional: si la tiene,
// la nota aparece en ese día del calendario.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('texto', 1000);
            $table->date('fecha')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_notas');
    }
};
