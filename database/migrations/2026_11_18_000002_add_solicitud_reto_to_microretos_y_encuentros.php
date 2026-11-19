<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de retos (ver create_solicitudes_reto_table):
 *  - microretos.solicitud_reto_id: de qué solicitud salió el reto.
 *  - microretos.en_recamara: reto generado para un docente que aún no está en la plataforma. Solo
 *    lo ve DuaLab: queda fuera de las bibliotecas de los centros (aunque su empresa sea de uno),
 *    de la empresa y del catálogo público.
 *  - encuentros.solicitud_reto_id: encuentro creado a partir de la sesión que propuso el docente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microretos', function (Blueprint $table) {
            $table->foreignId('solicitud_reto_id')->nullable()->after('centro_id')
                ->constrained('solicitudes_reto')->nullOnDelete();
            $table->boolean('en_recamara')->default(false)->after('solicitud_reto_id')->index();
        });

        Schema::table('encuentros', function (Blueprint $table) {
            $table->foreignId('solicitud_reto_id')->nullable()->after('microproyecto_id')
                ->constrained('solicitudes_reto')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('encuentros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitud_reto_id');
        });

        Schema::table('microretos', function (Blueprint $table) {
            $table->dropIndex(['en_recamara']);
            $table->dropColumn('en_recamara');
            $table->dropConstrainedForeignId('solicitud_reto_id');
        });
    }
};
