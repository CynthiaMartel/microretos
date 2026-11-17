<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Valoración final de la empresa (Taller de ideas, P3): cuando los grupos de un encuentro
        // creativo terminan, la empresa valora la propuesta entera viendo sus soluciones. Una por
        // encuentro (cada clase tiene sus resultados), desde un enlace con token que caduca.
        Schema::create('encuentro_valoraciones_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encuentro_id')->unique()->constrained('encuentros')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('token_expira_en');
            $table->timestamp('enviado_en')->nullable();      // el docente confirma que se lo hizo llegar
            $table->timestamp('respondido_en')->nullable();
            $table->unsignedTinyInteger('valoracion_global')->nullable(); // 1–5
            $table->json('respuestas')->nullable();
            $table->text('comentarios')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encuentro_valoraciones_empresa');
    }
};
