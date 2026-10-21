<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulos que imparte cada docente (los asigna el admin de su centro o el propio docente
 * desde «Mi usuario»). Sirve para limitar a quién se puede invitar como colaborador de un
 * encuentro: compañeros del mismo centro que compartan familia y módulo con el proyecto.
 * La familia no se guarda: se deduce del ciclo de cada módulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_modulo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // modulos.id es INT con signo (tabla creada con SQL en bruto)
            $table->integer('modulo_id');
            $table->foreign('modulo_id')->references('id')->on('modulos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'modulo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_modulo');
    }
};
