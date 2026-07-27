<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipo_tareas', function (Blueprint $table) {
            // 'proceso' = lluvia de ideas / organización del trabajo.
            // 'detalle_solucion' = tareas que detallan y construyen la propuesta concreta del equipo.
            $table->enum('tipo', ['proceso', 'detalle_solucion'])->default('proceso')->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('equipo_tareas', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
