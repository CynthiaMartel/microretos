<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Columna estructurada equivalente a Microreto.evaluacion_oficial (ra_id/ce_ids reales
// en vez de texto libre). `ra_ce` se conserva — el controller sigue derivándola
// automáticamente a partir de esta columna para no romper las vistas que aún la leen
// (landing de empresa, detalle de proyecto, export a PDF).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microproyectos', function (Blueprint $table) {
            $table->json('evaluacion_oficial')->nullable()->after('ra_ce');
        });
    }

    public function down(): void
    {
        Schema::table('microproyectos', function (Blueprint $table) {
            $table->dropColumn('evaluacion_oficial');
        });
    }
};
