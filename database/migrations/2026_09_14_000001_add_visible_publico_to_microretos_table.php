<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microretos', function (Blueprint $table) {
            // Opt-in explícito por reto para el escaparate público del frontoffice
            // (dualab.es / info.dualab.es) — por defecto ningún microreto es visible
            // fuera de la herramienta hasta que un docente/admin lo marque a propósito.
            $table->boolean('visible_publico')->default(false)->after('es_simulado');
        });
    }

    public function down(): void
    {
        Schema::table('microretos', function (Blueprint $table) {
            $table->dropColumn('visible_publico');
        });
    }
};
