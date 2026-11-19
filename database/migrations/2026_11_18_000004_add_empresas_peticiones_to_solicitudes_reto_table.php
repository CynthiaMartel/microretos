<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de retos con varias peticiones (p. ej. un reto de Informática y otro de
 * Administración): DuaLab elige una empresa para cada una. { "0": empresa_id, "1": empresa_id }.
 * La columna empresa_id se queda como empresa por defecto (solicitudes de una sola petición).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_reto', function (Blueprint $table) {
            $table->json('empresas_peticiones')->nullable()->after('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reto', function (Blueprint $table) {
            $table->dropColumn('empresas_peticiones');
        });
    }
};
