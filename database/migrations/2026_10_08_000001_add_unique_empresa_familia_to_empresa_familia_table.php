<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una empresa puede tener varias familias, pero cada familia una sola vez: la empresa
 * es una única fila en `empresas` y el pivot guarda una fila por familia vinculada.
 * Antes del índice se eliminan las filas repetidas (misma empresa + misma familia),
 * conservando la más antigua. Las filas legacy con familia_id NULL no chocan con el
 * índice (MySQL admite varios NULL en un UNIQUE).
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicadas = DB::table('empresa_familia as a')
            ->join('empresa_familia as b', function ($join) {
                $join->on('a.empresa_id', '=', 'b.empresa_id')
                    ->on('a.familia_id', '=', 'b.familia_id')
                    ->on('a.id', '>', 'b.id');
            })
            ->distinct()
            ->pluck('a.id');

        if ($duplicadas->isNotEmpty()) {
            DB::table('empresa_familia')->whereIn('id', $duplicadas)->delete();
        }

        Schema::table('empresa_familia', function (Blueprint $table) {
            $table->unique(['empresa_id', 'familia_id'], 'empresa_familia_empresa_familia_unique');
        });
    }

    public function down(): void
    {
        // La FK de empresa_id conserva su propio índice (empresa_familia_empresa_id_foreign)
        Schema::table('empresa_familia', function (Blueprint $table) {
            $table->dropUnique('empresa_familia_empresa_familia_unique');
        });
    }
};
