<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Para qué quiere trabajar la empresa con el alumnado de cada familia (p. ej. una empresa
 * de informática vinculada a Comercio y Marketing: "Departamento de marketing: campaña de
 * lanzamiento"). Lo usa el Generador de Retos para plantear el reto desde esa necesidad.
 * Opcional: las filas existentes quedan a NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_familia', function (Blueprint $table) {
            $table->string('interes', 500)->nullable()->after('familia');
        });
    }

    public function down(): void
    {
        Schema::table('empresa_familia', function (Blueprint $table) {
            $table->dropColumn('interes');
        });
    }
};
