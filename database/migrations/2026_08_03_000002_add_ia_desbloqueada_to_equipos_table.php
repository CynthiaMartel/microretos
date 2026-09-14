<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            // Se activa una vez el equipo introduce el codigo_ia de su encuentro —
            // desbloquea los botones "Sugerir con IA" del workspace para todo el equipo.
            $table->boolean('ia_desbloqueada')->default(false)->after('fase_actual');
        });
    }

    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn('ia_desbloqueada');
        });
    }
};
