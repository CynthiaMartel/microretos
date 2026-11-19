<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de retos a la papelera (borrado seguro, como empresas y centros): el superadmin la
 * mueve desde Central y la restaura o la borra del todo en Red DuaLab → Papelera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_reto', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reto', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
