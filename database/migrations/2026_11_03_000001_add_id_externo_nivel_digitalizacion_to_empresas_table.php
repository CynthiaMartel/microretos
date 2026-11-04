<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos datos nuevos de empresa, ambos opcionales (las empresas existentes quedan en null):
 * - nivel_digitalizacion (Bajo / Medio / Alto): el Generador de Retos lo usa para ajustar
 *   las propuestas a lo que la empresa puede adoptar.
 * - id_externo: clave del paquete de importación de DuaLab (EMP-0001…), para reimportar
 *   sin duplicar. Solo la fija el comando de importación; las copias en centros no la heredan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('nivel_digitalizacion', 10)->nullable()->after('tamano');
            $table->string('id_externo', 50)->nullable()->unique()->after('copiada_de_id');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropUnique(['id_externo']);
            $table->dropColumn(['nivel_digitalizacion', 'id_externo']);
        });
    }
};
