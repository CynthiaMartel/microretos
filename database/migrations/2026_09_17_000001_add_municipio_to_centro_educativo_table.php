<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// El wizard de microproyecto (StartupDayWizard.vue) ya lee `centro.municipio` al elegir
// centro educativo, pero la columna nunca existió — siempre llegaba vacío. Se añade aquí
// como columna real (no solo para datos de demo: cualquier centro real podrá tener su
// municipio informado desde ahora).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centro_educativo', function (Blueprint $table) {
            $table->string('municipio')->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('centro_educativo', function (Blueprint $table) {
            $table->dropColumn('municipio');
        });
    }
};
