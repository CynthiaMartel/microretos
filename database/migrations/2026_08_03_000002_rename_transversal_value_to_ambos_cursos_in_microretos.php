<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de datos: el sentinel de microretos.curso para "cruza 1º y 2º" pasa de
 * 'transversal' a 'ambos_cursos' — nombre más explícito, ya que 'transversal' quedó
 * reservado exclusivamente para microretos.multimodulo (varios módulos de un mismo curso).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('microretos')->where('curso', 'transversal')->update(['curso' => 'ambos_cursos']);
    }

    public function down(): void
    {
        DB::table('microretos')->where('curso', 'ambos_cursos')->update(['curso' => 'transversal']);
    }
};
