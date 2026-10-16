<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Centro propio del reto. Hasta ahora el centro se deducía de la empresa, y al purgar una
 * empresa de la papelera (empresa_id → NULL) el reto desaparecía de la biblioteca de su
 * centro. Con centro_id el reto sigue siendo del centro aunque su empresa ya no exista.
 *
 * Backfill desde la empresa (incluidas las de la papelera): primero por la FK centro_id,
 * después por el nombre legacy centro_educativo. Los retos de demo no tienen empresa y
 * se quedan sin centro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microretos', function (Blueprint $table) {
            // nullOnDelete: purgar un centro no debe bloquearse por sus retos (igual que limpiarCentro con las empresas)
            $table->foreignId('centro_id')->nullable()->after('empresa_id')
                ->constrained('centro_educativo')->nullOnDelete();
        });

        DB::table('microretos')->whereNull('centro_id')->whereNotNull('empresa_id')->update([
            'centro_id' => DB::raw('(select empresas.centro_id from empresas where empresas.id = microretos.empresa_id)'),
        ]);

        DB::table('microretos')->whereNull('centro_id')->whereNotNull('empresa_id')->update([
            'centro_id' => DB::raw('(select centro_educativo.id from centro_educativo
                inner join empresas on empresas.centro_educativo = centro_educativo.nombre
                where empresas.id = microretos.empresa_id limit 1)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('microretos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('centro_id');
        });
    }
};
