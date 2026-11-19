<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Borrar captaciones (empresas y centros) y retos en borrador desde Central → Captaciones: van a la
// papelera (soft delete), como el resto; restaurar o borrar del todo es de la Papelera (superadmin).
// Así la firma y los consentimientos de una captación no se pierden por un borrado por error.
return new class extends Migration
{
    private const TABLAS = ['empresa_captaciones', 'centro_captaciones', 'microreto_borradores'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, fn (Blueprint $t) => $t->softDeletes());
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, fn (Blueprint $t) => $t->dropSoftDeletes());
        }
    }
};
