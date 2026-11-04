<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnóstico de madurez del área que da problemas a la empresa (biblioteca de DuaLab, ver
 * App\Support\BibliotecaRetos): {"area": ..., "procedimiento": 0-4, "herramientas": 0-4,
 * "medicion": 0-4, "responsables": 0-4}. Opcional: las empresas sin él generan como siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->json('diagnostico_madurez')->nullable()->after('nivel_digitalizacion');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('diagnostico_madurez');
        });
    }
};
