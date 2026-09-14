<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuentros', function (Blueprint $table) {
            $table->foreignId('centro_educativo_id')
                  ->nullable()
                  ->after('centro_educativo')
                  ->constrained('centro_educativo')
                  ->nullOnDelete();
        });

        // Backfill: resuelve el id a partir del string legacy 'centro_educativo' ya
        // guardado en cada encuentro, para que el scope por id no pierda visibilidad
        // de encuentros creados antes de esta migración.
        DB::table('encuentros')
            ->join('centro_educativo', 'centro_educativo.nombre', '=', 'encuentros.centro_educativo')
            ->update(['encuentros.centro_educativo_id' => DB::raw('centro_educativo.id')]);
    }

    public function down(): void
    {
        Schema::table('encuentros', function (Blueprint $table) {
            $table->dropForeign(['centro_educativo_id']);
            $table->dropColumn('centro_educativo_id');
        });
    }
};
