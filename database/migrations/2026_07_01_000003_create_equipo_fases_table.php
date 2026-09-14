<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipo_fases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->tinyInteger('numero_fase');           // 0-4
            $table->json('datos')->nullable();            // contenido específico de cada fase
            $table->boolean('completada')->default(false);
            $table->timestamp('fecha_completada')->nullable();
            $table->boolean('validado_docente')->default(false);
            $table->timestamp('fecha_validacion_docente')->nullable();
            $table->decimal('nota_docente', 4, 2)->nullable();
            $table->text('observaciones_docente')->nullable();
            $table->unique(['equipo_id', 'numero_fase']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipo_fases');
    }
};
