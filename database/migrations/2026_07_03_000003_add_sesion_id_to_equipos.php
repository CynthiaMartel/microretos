<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->foreignId('sesion_id')
                  ->nullable()
                  ->after('microproyecto_id')
                  ->constrained('sesiones')
                  ->nullOnDelete();
        });

        // Backfill desde microproyectos.sesion_id
        DB::statement('
            UPDATE equipos e
            JOIN microproyectos mp ON mp.id = e.microproyecto_id
            SET e.sesion_id = mp.sesion_id
            WHERE mp.sesion_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropForeign(['sesion_id']);
            $table->dropColumn('sesion_id');
        });
    }
};
