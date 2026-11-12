<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que trae el formulario de empresas de la web y no tiene columna propia (si colabora ya con
 * centros, qué le interesa, sector tal como lo escribió…): texto para el superadmin al validar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_captaciones', function (Blueprint $table) {
            $table->text('notas')->nullable()->after('expectativas_alumno');
        });
    }

    public function down(): void
    {
        Schema::table('empresa_captaciones', function (Blueprint $table) {
            $table->dropColumn('notas');
        });
    }
};
