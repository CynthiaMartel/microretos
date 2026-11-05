<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retos generados por el comercial delante de la empresa. Viven aparte de `microretos` a
 * propósito: así nunca aparecen en la biblioteca, el escaparate ni los encuentros hasta que
 * el superadmin los publica (se crea entonces un Microreto por cada centro elegido).
 * datos: el reto tal cual lo devolvió la generación (sin firmas), más su contexto académico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('microreto_borradores', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            // ciclos_formativos.id es INT con signo (tabla creada con SQL en bruto)
            $table->integer('ciclo_id')->nullable();
            $table->foreign('ciclo_id')->references('id')->on('ciclos_formativos')->nullOnDelete();
            $table->string('familia', 255)->nullable();
            $table->json('datos');
            // pendiente → publicado | descartado
            $table->string('estado', 20)->default('pendiente')->index();
            $table->foreignId('revisado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microreto_borradores');
    }
};
