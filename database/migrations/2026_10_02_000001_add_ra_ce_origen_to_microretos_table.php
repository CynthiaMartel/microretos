<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién eligió los RA/CE de evaluacion_oficial al generar el microreto:
 * 'ia' (la IA decidió), 'docente' (todos fijados a mano) o 'mixto' (unos módulos
 * fijados y otros a elección de la IA). Nullable: los retos anteriores no lo registraron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microretos', function (Blueprint $table) {
            $table->string('ra_ce_origen', 20)->nullable()->after('multimodulo');
        });
    }

    public function down(): void
    {
        Schema::table('microretos', function (Blueprint $table) {
            $table->dropColumn('ra_ce_origen');
        });
    }
};
