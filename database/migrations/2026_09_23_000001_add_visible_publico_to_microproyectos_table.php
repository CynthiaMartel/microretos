<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Opt-in manual por proyecto, igual que microretos.visible_publico (ver
// 2026_09_14_000001_add_visible_publico_to_microretos_table): por defecto ningún
// proyecto es visible en el escaparate público (dualab.es) hasta que un docente/admin
// lo marque a propósito desde el backoffice. Solo tiene sentido en proyectos completados
// — eso se exige a nivel de aplicación (PublicMicroproyectoCatalogoController /
// MicroproyectoController::toggleVisiblePublico), no aquí.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microproyectos', function (Blueprint $table) {
            $table->boolean('visible_publico')->default(false)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('microproyectos', function (Blueprint $table) {
            $table->dropColumn('visible_publico');
        });
    }
};
