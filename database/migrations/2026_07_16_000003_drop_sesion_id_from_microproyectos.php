<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// `microproyectos.sesion_id` era un puntero inverso (microproyecto → sesión)
// del diseño ANTERIOR a la inversión de la relación hecha en
// 2026_07_08_000001_invert_sesion_microproyecto_relationship. Desde esa
// migración la relación canónica es la contraria: Encuentro belongsTo
// Microproyecto (encuentros.microproyecto_id, con FK real y ON DELETE SET
// NULL). Esta columna quedó vestigial y redundante — se elimina en vez de
// renombrarla para no arrastrar el diseño antiguo al nuevo nombre "encuentro".
// `equipos.encuentro_id` (también ON DELETE SET NULL) sigue siendo la FK real
// para saber a qué encuentro pertenece un equipo; no se toca aquí.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('microproyectos', 'sesion_id')) {
            Schema::table('microproyectos', function (Blueprint $table) {
                $table->dropForeign(['sesion_id']);
                $table->dropColumn('sesion_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('microproyectos', 'sesion_id')) {
            Schema::table('microproyectos', function (Blueprint $table) {
                $table->foreignId('sesion_id')
                      ->nullable()
                      ->after('microreto_id')
                      ->constrained('encuentros')
                      ->nullOnDelete();
            });
        }
    }
};
