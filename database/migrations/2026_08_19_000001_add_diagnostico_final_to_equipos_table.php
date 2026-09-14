<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            // Diagnóstico final generado por IA a partir de todo el workspace del equipo,
            // una vez completadas las 5 fases — se persiste para no volver a llamar a la
            // IA cada vez que el docente reabre el equipo (ver EquipoGestionController::diagnosticoFinal).
            $table->json('diagnostico_final')->nullable()->after('ia_desbloqueada');
            $table->timestamp('diagnostico_generado_en')->nullable()->after('diagnostico_final');
        });
    }

    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn(['diagnostico_final', 'diagnostico_generado_en']);
        });
    }
};
