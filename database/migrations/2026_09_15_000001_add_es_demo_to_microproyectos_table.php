<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Marca directa de "generado por los comandos demo:generar-*", para no tener que
// trazar Microproyecto/Encuentro/Equipo subiendo por FK hasta una Empresa.es_simulada
// o un Microreto.es_simulado en cada consulta de scoping (ver DemoGenerarProyectos,
// DemoGenerarEncuentrosEquipos, DemoBorrarFicticios).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microproyectos', function (Blueprint $table) {
            $table->boolean('es_demo')->default(false)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('microproyectos', function (Blueprint $table) {
            $table->dropColumn('es_demo');
        });
    }
};
